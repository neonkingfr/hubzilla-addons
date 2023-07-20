import importlib
import os
import pandas as pd
import time
import datetime
import logging
import sys
import faces_exiftool
import faces_util
import json

deepface_spec = importlib.util.find_spec("deepface")
if deepface_spec is not None:
    import faces_finder
    import faces_recognizer


class Worker:

    def __init__(self):
        self.proc_id = None
        self.db = None
        self.finder = None
        self.recognizer = None
        self.dirImages = None
        self.file_name_config = "config.json"
        self.file_name_config_thresholds = "thresholds.json"
        self.file_name_face_representations = "faces.gzip"
        self.file_name_faces = "faces.json"
        self.file_name_names = "names.json"
        self.file_name_faces_statistic = "face_statistics.csv"
        self.file_name_models_statistic = "model_statistics.csv"
        self.file_name_probe = "probe.csv"
        self.file_name_most_similar = "faces_most_similar.gzip"
        self.file_name_share = "share.json"
        self.file_pattern_shared = "shared_%.json"
        self.dir_addon = "faces"

        # Watch this!
        # What happens if self.keep_history = True
        # The file .faces.gzip will be written if the face recognition finds something.
        # Q: Why does it matter?
        # A: In case the face recognition runs for ZOT/Nomad and the channel has clones
        #    the written file will trigger a file sync of .faces.gzip. between the clones (servers).
        self.keep_history = False  # True/False
        self.statistics = False  # True/False
        self.columnsToIncludeAll = ["model", "detector", "duration_detection", "duration_representation",
                                    "time_created", "distance", "distance_metric", "duration_recognized", "width",
                                    "emotions", "gender_prediction", "races"]
        self.columnsToInclude = []  # ["model", "detector"] extra columns if faces.json / faces.cs
        self.columnsSort = ["file", "position", "face_nr", "name", "name_recognized", "time_named", "exif_date",
                            "detector", "model", "mtime"]
        self.timeLastAliveSignal = 0
        self.timeToWait = 10  # second
        self.timeBackUp = 60 * 5  # second
        self.lockFile = None
        self.RUNNING = "running"
        self.FINISHED = "finished"
        self.pid = ""
        self.exiftool = None
        self.remove_detectors = ""
        self.remove_models = ""
        self.is_remove_names = False
        self.IGNORE = "-ignore-"
        self.sort_column = "mtime"
        self.sort_ascending = False

        self.folder = None
        self.file_embeddings = None
        self.file_faces = None
        self.file_names = None  # set by user via web browser (from outside)
        self.file_demography = None
        self.file_config = None
        self.file_config_thresholds = None
        self.file_face_statistics = None
        self.file_model_statistics = None
        self.file_probe = None
        self.file_most_similar = None
        self.file_share = None
        self.channel = None
        self.util = faces_util.Util()        
        self.has_new_names = False

        self.ram_allowed = 80  # %

        self.status_suffix = ""
        
    def init_finder(self):
        deepface_specs = importlib.util.find_spec("deepface")
        if deepface_specs is not None:
            self.finder = faces_finder.Finder()
            self.finder.util = self.util
        else:
            logging.error("FAILED to set finder. Reason: module deepface not found")
            sys.exit(1)
        
    def init_recognizer(self):
        deepface_specs = importlib.util.find_spec("deepface")
        if deepface_specs is not None:
            self.recognizer = faces_recognizer.Recognizer()
        else:
            logging.critical("FAILED to set finder. Reason: module deepface not found")
            sys.exit(1)

    def set_db(self, db):
        self.db = db
        logging.debug("database was set")

    def configure(self):
        
        json = self.read_config_file()

        if "worker" in json:

            # --------------------------------------------------------------------------------------------------------------
            # set by admin in frontend

            if "ram" in json["worker"]:
                if isinstance(json["worker"]["ram"], str) and json["worker"]["ram"].isdigit():
                    self.ram_allowed = int(json["worker"]["ram"])
                else:
                    self.ram_allowed = json["worker"]["ram"]

            # --------------------------------------------------------------------------------------------------------------
            # not set by user in frontend
            if "sort_column" in json["worker"]:
                self.sort_column = json["worker"]["sort_column"]

            if "sort_ascending" in json["worker"]:
                self.sort_ascending = json["worker"]["sort_ascending"]

            if "interval_alive_signal" in json["worker"]:
                if isinstance(json["worker"]["interval_alive_signal"], str) \
                        and json["worker"]["interval_alive_signal"].isdigit():
                    self.timeToWait = int(json["worker"]["interval_alive_signal"])
                else:
                    self.timeToWait = json["worker"]["interval_alive_signal"]

            if "interval_backup_detection" in json["worker"]:
                if isinstance(json["worker"]["interval_backup_detection"], str) \
                        and json["worker"]["interval_backup_detection"].isdigit():
                    self.timeBackUp = int(json["worker"]["interval_backup_detection"])
                else:
                    self.timeBackUp = json["worker"]["interval_backup_detection"]

        logging.debug("config: ram=" + str(self.ram_allowed))
        logging.debug("config: sort_column=" + str(self.sort_column))
        logging.debug("config: sort_ascending=" + str(self.sort_ascending))
        logging.debug("config: interval_alive_signal=" + str(self.timeToWait))
        logging.debug("config: interval_backup_detection=" + str(self.timeBackUp))

        # --------------------------------------------------------------------------------------------------------------
        # set by user in frontend

        if "statistics" in json:
            self.statistics = json["statistics"][0][1]
        logging.debug("config: statistics=" + str(self.statistics))

        if "history" in json:
            self.keep_history = json["history"][0][1]
        logging.debug("config: keep_history=" + str(self.keep_history))

        # --------------------------------------------------------------------------------------------------------------
        # set directly by calling script

        logging.debug("config: rm_names=" + str(self.is_remove_names))
        logging.debug("config: rm_models=" + str(self.remove_models))
        logging.debug("config: rm_detectors=" + str(self.remove_detectors))
        # --------------------------------------------------------------------------------------------------------------

        # configure finder
        self.finder.configure(json)
        self.finder.ram_allowed = self.ram_allowed
        # configure recognizer
        self.recognizer.configure(json)

        self.exiftool = faces_exiftool.ExifTool()

        self.util.is_css_position = self.finder.css_position

    def run(self, dir_images, proc_id, own_channel_id, is_recognize, is_probe):
        logging.info("start dir=" + dir_images + ", proc_id=" + proc_id + ", own_channel_id=" +
                     str(own_channel_id) + ", recognize=" + str(is_recognize) + ", probe=" + str(is_probe))

        self.proc_id = proc_id
        if is_recognize:
            self.status_suffix = str(own_channel_id)
        self.write_alive_signal(self.RUNNING)

        # get all channel_id's (users) that have the app faces installed
        query = "select app_channel from app where app_plugin = 'faces' AND app_channel != 0"
        data = {}
        app_channels = self.db.select(query, data)

        # loop through every user who has the faces addon switched on
        for app_channel in app_channels:

            # --------------------------------------------
            # next user (channel that activated the addon)
            # --------------------------------------------

            self.channel = app_channel[0]
            logging.debug("Channel " + str(self.channel) + " - starting...")

            query = "SELECT channel_address FROM `channel` WHERE `channel_id` = " + str(self.channel)
            data = {}
            channel_addresses = self.db.select(query, data)
            channel_address = channel_addresses[0][0]

            dir_store, tail = os.path.split(dir_images)
            self.dirImages = os.path.join(dir_store, channel_address)
            if os.access(self.dirImages, os.R_OK) is False:
                logging.error("can not read directory " + self.dirImages)
                self.stop()

            # get all folders containing images for a user
            query = "SELECT folder FROM `attach` WHERE `uid` = %s AND `filename` = %s"
            data = (self.channel, self.file_name_faces)
            folders = self.db.select(query, data)
            if len(folders) == 0:
                logging.info("no files " + self.file_name_faces + " for channel " + str(self.channel))
                continue
            
            # every channel has it's own configuration file
            self.init_finder()
            self.init_recognizer()
            # configure both finder and recognizer
            self.configure()

            if not is_recognize:
                # --------------------------------------------
                # Detection
                # --------------------------------------------
                # detect faces in all folders containing images for a user
                for f in folders:
                    self.folder = f[0]
                    self.process_dir(own_channel_id)

            if own_channel_id == self.channel:
                # --------------------------------------------
                # Recognition
                # --------------------------------------------
                # recognize only
                # - if set as parameter from caller
                # - for the user who called the script
                if is_probe and is_recognize:
                    folders = self.get_probe_folders()
                    if not folders:
                        continue
                self.recognize(folders, is_probe)
            else:
                logging.debug("no recognition, user channel  " + str(own_channel_id) + " != " + str(self.channel))
                continue

        self.write_alive_signal(self.FINISHED)
        logging.info("finished dir=" + dir_images + ", proc_id=" + proc_id + ", own_channel_id=" + str(own_channel_id))

    def get_probe_folders(self):
        query = "SELECT folder FROM `attach` WHERE `uid` = %s AND `filename` = %s AND `display_path` LIKE 'faces/probe/%'"
        data = (self.channel, self.file_name_faces)
        folders = self.db.select(query, data)
        if len(folders) == 0:
            logging.info("no probe pictures/names for channel " + str(self.channel))
            return False
        return folders

    def read_config_file(self):
        logging.debug("channel " + str(self.channel) + " - read config file")
        if self.check_file_by_channel(self.file_name_config) is False:
            logging.debug("channel " + str(self.channel) + " - could not read config file " + self.file_name_config)
            return {}
        if os.path.exists(self.file_config):
            if os.stat(self.file_config).st_size == 0:
                logging.debug("channel " + str(self.channel) + " - config file is empty " + self.file_config)
                return {}
        with open(self.file_config, "r") as f:
            conf = json.load(f)
            return conf

    def read_config_thresholds_file(self):
        logging.debug("channel " + str(self.channel) + " - read config thresholds file")
        if self.check_file_by_channel(self.file_name_config_thresholds) is False:
            logging.debug("channel " + str(self.channel) + " - could not read file " + self.file_name_config_thresholds)
            return
        if os.path.exists(self.file_config_thresholds):
            if os.stat(self.file_config_thresholds).st_size == 0:
                logging.debug("channel " + str(self.channel) + " empty thresholds conf " + self.file_config_thresholds)
                return
        with open(self.file_config_thresholds, "r") as f:
            conf = json.load(f)
            self.recognizer.configure_thresholds(conf, self.finder.model_names)

    def process_dir(self, own_channel_id):
        logging.debug("Start detection with directory " + self.folder + " / channel " + str(self.channel))

        # -------------------------------------------------------------
        # Prepare files
        #
        # read absolute paths of some files from the database and check
        # - access rights to file system and
        # - file content
        if self.check_file_by_name(self.file_name_faces) is False:
            return
        if self.check_file_by_name(self.file_name_face_representations) is False:
            return
        self.check_file_by_name(self.file_name_names)  # for cleanup

        # -------------------------------------------------------------
        # Cleanup
        #
        # - Remove faces of images that do not exist anymore in a subdirectory
        # - Remove faces for certain detectors and/or models (if set as parameter by the caller)
        if self.channel == own_channel_id:
            self.cleanup()
            self.remove_names()

        # -------------------------------------------------------------
        # Detect
        #
        # process all images
        self.detect()

    # Find and store all face representations for face recognition.
    # The face representations are stored in binary pickle file.
    def detect(self):
        logging.debug("START DETECTION, CREATION of EMBEDDINGS and FACIAL ATTRIBUTES ---")

        # get all embeddings and attributes for the images in directory
        df = self.get_face_representations()  # pandas.DataFrame holding all face representation

        if df is None:
            df = self.util.create_frame_embeddings()

        images = self.get_images()
        if len(images) == 0:
            logging.debug("No new images in directory")
            return

        logging.debug("searching for faces in " + str(len(images)) + " images")

        # --------------------------------------------------------------------------------------------------------------
        # iterate all images in a directory
        time_start = time.time()
        embeddings_file_has_changed = False
        for image in images:

            path = image[0]
            os_path_on_server = os.path.join(self.dirImages, image[1])

            logging.debug(path)

            # ----------------------------------------------------------------------------------------------------------
            # iterate all activated detectors to
            # - find new combinations for each image of detector and
            #   a. model (face recognition) and
            #   b. attributes (emotion, gender, age, race)
            # - create and store embeddings for each face
            # - analyse the attributes of each face
            for key in self.finder.detector_names:
                logging.debug(path + " " + key)

                result = self.finder.detect(path, os_path_on_server, key, df)
                self.write_alive_signal(self.RUNNING)  # this includes ram check
                df = result[0]
                if result[1]:
                    embeddings_file_has_changed = True

                elapsed_time = time.time() - time_start
                if elapsed_time > self.timeBackUp:
                    if self.store_face_presentations(df) is False:
                        return
                    logging.info("elapsed time " + str(elapsed_time) + " > " + str(self.timeBackUp))
                    time_start = time.time()

        if not embeddings_file_has_changed:
            logging.debug("nothing changed in this directory")
            return
        # ---
        # Find same faces by position in images.
        # Explanation:
        # - faces are found by different detectors (and for different models)
        # - a face is the same if found
        #   o in same file
        #   o at same position (x, y, h, w)
        df = self.util.number_unique_faces(df)

        # Copy names to faces that where found by new detectors or models
        df = self.util.copy_name_to_same_faces(df)

        # Why not storing the faces (faces.json) at this point of time?
        # - The face detection and the creation of the face representations are cpu, memory and time-consuming
        # - In praxis
        #   o The user uploads some or many more pictures to a directory
        #   o The user opens the addon. This will start the face detection that runs for a couple of minutes
        #   o Meanwhile the user clicks on some faces and name the faces or changes some face names. The changes
        #     are written into to faces.json
        # - After some time the face detection has ended and will write faces.json.
        #   This overwrites the face names the user has set in the meantime.
        logging.info("finished detection of faces in " + str(len(images)) + " images")
        df = self.write_exif_dates(df)

        if self.store_face_presentations(df) is False:
            return
        self.init_face_names(df)

    # -------------------------------------------------------------
    # Basic steps:
    # 1. In every directory
    #    - Load DataFrame holding the face representations (faces.gzip)
    #    - Load DataFrame holding the names (faces.json)
    #    - Write all names (set by the user) from df (faces.json) to DataFrame holding the face representations
    # 2. Append the df's from each directory to get one big DataFrame holding all face representation and known names
    # 4. Try to recognize faces in the big df (match embeddings)
    # 5. Check if the recognition found new faces or changed names
    # 6. Write the names (faces.json) if the values for "name" or "name_recognized" have changed.
    #    This browser will read this file.
    # 7. If history is switched on: write faces.gzip (including now all face embedding AND names)
    #
    def recognize(self, folders, is_probe):

        # Read the configuration set by admin/user
        if self.recognizer.thresholds_config is None:
            self.read_config_thresholds_file()
        probe_results = []
        probe_cols = []

        logging.info("START COMPARING FACES - for channel " + str(self.channel) + " - Loading all necessary data...")

        df = self.load_face_names(folders)
        if df is None:
            return

        def include_as_training_data(row):
            w = row['pixel']
            if w < self.finder.min_width_train:
                return 0
            return 1

        def include_as_result_data(row):
            w = row['pixel']
            if w < self.finder.min_width_result:
                return 0
            return 1

        df['min_size_train'] = df.apply(include_as_training_data, axis=1)
        df['min_size_result'] = df.apply(include_as_result_data, axis=1)
        
        # self.has_new_names = False   # DELETE this line
        df_best_trained = None
        found_training_data_from_me = self.check_share_file()
        if not self.has_new_names:            
            if found_training_data_from_me:
                df_best_trained = self.get_training_data()
                if df_best_trained is not None:
                    # do not look for most similar faces again
                    self.recognizer.most_similar_apply = False

        found_training_data_from_others = self.read_shared_faces()

        for key in self.finder.detector_names:
            detector = key

            logging.debug(detector + " = detector: Get all faces with a name, face representation and min width")
            df_no_name = df.loc[
                (df['name'] == "") &
                (df['detector'] == detector) &
                (df['name'] != self.IGNORE) &
                (df['duration_representation'] != 0.0) &
                (df['min_size_result'] == 1) &
                (df['time_named'] == ""), ['id', 'representation', 'model', 'detector', 'file', 'face_nr']]

            # Loop through models
            # - loop through the ordered list models (start parameter)
            # - optionally stop recognition after a first match (start parameter)
            models = df.model.unique()
            for model in models:
                if model not in self.finder.model_names:
                    continue

                df_model = df_no_name.loc[df_no_name['model'] == model]
                if len(df_model) == 0:
                    logging.debug(model + " " + detector + " - No faces to search")
                    continue

                # Set back previous results
                # Why?
                # - parameters might change (distance metrics, min face width)
                # - show results for parameters of this run only
                df.loc[(df['model'] == model) &
                    (df['detector'] == detector) &
                    (df['name'] == "") &  # keep history of recognized names for statitstics
                    (df['name'] != self.IGNORE) &  # the user has set this face to "ignore"
                    (df['duration_representation'] != 0.0) &
                    (df['time_named'] == ""),  # the user has set the name to "unknown"
                    ['name_recognized', 'distance', 'distance_metric', 'duration_recognized']] = ["", -1.0, "", 0]

                if not self.has_new_names and found_training_data_from_me and df_best_trained is not None:
                    # Filter faces as training data
                    logging.debug(model + " " + detector + " - collecting faces as training data from a file holding the most similar faces...")
                    df_training_data = df_best_trained.loc[
                        (df_best_trained['model'] == model) &
                        (df_best_trained['detector'] == detector) &
                        (df_best_trained['name'] != "") &            # kind of input validation
                        (df_best_trained['name'] != self.IGNORE) &   # kind of input validation
                        (df_best_trained['representation'] != ""), ['representation', 'name', 'model', 'detector']]                    
                else:
                    # Filter faces as training data
                    logging.debug(model + " " + detector + " - collecting faces as training data from all named faces...")
                    df_training_data = df.loc[
                        (df['model'] == model) &
                        (df['detector'] == detector) &
                        (df['name'] != "") &
                        (df['name'] != self.IGNORE) &
                        (df['min_size_train'] == 1) &
                        (df['representation'] != ""), ['representation', 'name', 'model', 'detector']]

                df_training_data_shared = None
                if found_training_data_from_others is not None and not is_probe:
                    # get all faces shared by your contacts for the current detector and model
                    df_training_data_shared = found_training_data_from_others.loc[
                        (found_training_data_from_others['model'] == model) &
                        (found_training_data_from_others['detector'] == detector), ['representation', 'name', 'model', 'detector']]
                    if len(df_training_data_shared) < 1:
                        df_training_data_shared = None

                if len(df_training_data) == 0 and df_training_data_shared is None:
                    logging.debug(model + " " + detector + " - No training data (names set)")
                    continue

                df_best_trained_model = self.recognizer.train(df_training_data, model)

                if self.has_new_names:
                    if df_best_trained is not None:
                        df_best_trained = pd.concat([df_best_trained, df_best_trained_model], ignore_index=True)
                    else:
                        df_best_trained = df_best_trained_model

                if df_training_data_shared is not None and not is_probe:
                    # train the model with faces shared by your contacts
                    logging.debug(model + " " + detector + " - appending " + str(len(df_training_data_shared)) + " face(s) as training data from shared faces...")
                    self.recognizer.names = pd.concat([self.recognizer.names, df_training_data_shared], ignore_index=True)

                if is_probe:
                    for metric in self.recognizer.distance_metrics:
                        probe_thresholds = self.recognizer.create_probe_thresholds(metric)
                        for t in probe_thresholds:
                            faces = self.recognizer.recognize(df_model, t)
                            if faces:
                                df = self.util.prepare_probe(df, model)
                                results = self.util.analyse_probe(
                                    df, faces, detector, model, t, probe_results, probe_cols)
                                probe_results = results[0]
                                probe_cols = results[1]
                else:
                    faces = self.recognizer.recognize(df_model, None)
                    if faces:
                        # write result of matches (faces found) into the embeddings file
                        for face in faces:
                            face_id = face['id']
                            df.loc[
                                df['id'] == face_id,
                                ['name_recognized', 'duration_recognized', 'distance', 'distance_metric']] = \
                                [
                                    face['name_recognized'],
                                    face['duration_recognized'],
                                    face['distance'],
                                    face['distance_metric']
                                ]
                        if self.recognizer.first_result:
                            df_no_name = self.util.remove_other_than_recognized(faces, df_no_name)
        if is_probe:
            result = self.util.build_probe_results(probe_results, probe_cols)
            self.write_probe(result)
            return

        most_effective_method = self.util.get_most_successful_method(df, False)

        df = df.drop('min_size_train', axis=1)
        df = df.drop('min_size_result', axis=1)

        logging.info("FINISHED COMPARING FACES. Checking for changes to save...")
        directories = df.directory.unique()
        for d in directories:
            df_directory = df[df['directory'] == d]
            self.store_face_names_if_changed(df_directory, d, most_effective_method)

        self.write_statistics(df, self.dirImages)
        self.store_training_data(df_best_trained)

    def load_face_names(self, folders):
        # df
        # df is the one big pandas.DataFrame that holds
        # - all face representations
        # - all names
        # - over all directories
        df = None
        for f in folders:
            self.folder = f[0]

            if self.check_file_by_name(self.file_name_face_representations) is False:
                return
            if self.check_file_by_name(self.file_name_faces) is False:
                return
            self.check_file_by_name(self.file_name_names)  # might be deleted but this is no serious condition

            # ---
            # Concatenate all face representations of all directories
            df_representations = self.get_face_representations()
            if df_representations is None:
                continue

            # ---
            # Read names in directory
            df_names = self.get_face_names()

            if df_names is not None:
                # ---
                # Write all known names into the face representations
                # Background:
                # This step is needed if
                #  - "statistics mode" / "history" is not switched on
                #  - names are written
                # Background:
                # If the "statistics mode" is NOT switched on then names are stored only in the file ".faces.json"
                # If the "statistics mode" IS switched on then names are stored along with the face representations
                # in the file ".faces.gzip".
                for face in df_names.itertuples():
                    df_representations.loc[(df_representations['id'] == face.id),
                                           ['name', 'time_named']] = [face.name, face.time_named]

            # Read face names set from outside (usually by the user via the browser)
            # Background:
            #   The user will name faces in the browser.
            #   The new or changed names will be written into a file names.json.
            #
            # What does happen in the next call?
            #   1. Read the new or changed names from names.json
            #   2. Remove the names from the file names.json (empty the data frame)
            df_representations = self.read_new_names(df_representations)

            # ---
            # Concatenate all face representations (including known names) of all directories
            if df is None:
                df = df_representations
            else:
                df = pd.concat([df, df_representations], ignore_index=True)

        if df is None:
            return df

        # column 'directory' to have a quick filter for directories
        def append_directory(x):
            path = x['file']
            index = path.rfind("/")
            if index < 0:
                directory = ""
            else:
                directory = path[0:index]
            return directory

        df['directory'] = df.apply(append_directory, axis=1)
        logging.debug("Appended directory to each face")

        return df

    # The main goal of this function is to avoid writing results (faces.gzip)
    # if nothing has changed after the process of face recognition.
    # Why does it matter?
    # A file is synchronized between Streams-/ Hubzilla clones as soon as a file is stored via webDAV
    #
    # param df_recognized data frame that is the result after the process of face recognition
    # param df_current    data frame that is read from the names.json
    def store_face_names_if_changed(self, df_recognized, faces_dir, most_effective_method):
        if self.check_file_by_display_path(self.file_name_face_representations, faces_dir) is False:
            return
        if self.check_file_by_display_path(self.file_name_faces, faces_dir) is False:
            return
        self.check_file_by_display_path(self.file_name_names, faces_dir)
        if self.init_face_names(df_recognized):
            return

        # show results for the activated models to the user (browser) only
        df_reduced = df_recognized[df_recognized.isin(self.finder.model_names).any(axis=1)]

        # remove all files without af face
        df_reduced = df_reduced.loc[(df_reduced['pixel'] != 0)]

        # remove faces the user wants to ignore (detected as face but is something else)
        # keys = df_reduced.loc[(df_reduced['name'] == self.IGNORE)].index
        # if len(keys) > 0:
        #     df_reduced = df_reduced.drop((keys))
        #     logging.debug(faces_dir + " - removed " + str(len(keys)) + " ignored faces in results")

        # "reduce" result file
        df = self.util.filter_by_last_named(df_reduced)

        df = self.util.keep_most_effectiv_method_only(df, most_effective_method)

        df_names = self.get_face_names()  # this will read new or changed names set by the use via the web browser

        # compare the content of the results (face recognition) with the content of the file containing the names
        # (that might have changed while the face recognition was running)
        # has any name or recognized name changed while the face recognition was running?
        has_names_changed = self.util.has_any_name_changed(df, df_names)
        if has_names_changed:
            self.write_results(df_recognized, df)
        else:
            logging.debug("faces have not changed. No need to store faces to file.")

        self.empty_names_for_browser()

    def read_new_names(self, df):
        df_browser = self.get_face_names_set_by_browser()
        if df_browser is None:
            return df
        name_count = len(df_browser)
        if name_count < 1:
            logging.debug("no new or changed names set from outside")
            return df

        # copy new or changed names.... the user might have changed names while the face recognition was running
        # ... into the file re
        for face in df_browser.itertuples():
            # copy changed names into the results (in fact all names but changed or new names are the reason)
            df.loc[(df['id'] == face.id), ['name', 'time_named']] = [face.name, face.time_named]
        logging.debug("copied " + str(name_count) + " names set from outside")

        df = self.util.copy_name_to_same_faces(df)
        
        # this will cause to create and write new training data
        self.has_new_names = True

        return df

    def empty_names_for_browser(self):
        if self.file_names is None:
            return
        if os.path.exists(self.file_names):
            f = open(self.file_names, 'w')
            f.write("")
            f.close()
        logging.debug(self.folder + " - wrote empty name file for browser " + self.file_names)

    def write_results(self, df_recognized, df_names):
        self.write_alive_signal(self.RUNNING)
        df_names.drop('directory', axis=1, inplace=True)
        logging.debug("faces have changed or where None before. Storing to file.")
        self.store_face_names(df_names)
        if self.keep_history:
            self.store_face_presentations(df_recognized)

    def get_images(self):
        query = ("SELECT display_path, os_path "
                 "FROM `attach` "
                 "WHERE `uid` = %s AND "
                 "`folder` = %s AND "
                 "`is_photo` = '1' AND "
                 "( `filetype` = 'image/jpeg' OR `filetype` = 'image/png')")
        data = (self.channel, self.folder)
        images = self.db.select(query, data)
        if len(images) == 0:
            logging.debug(self.folder + " - no images in this directory")
        return images

    def check_share_file(self):
        # this function is to have the same "interace" for the webdav version
        return self.check_file_by_channel(self.file_name_most_similar)

    def check_file_by_name(self, file_name):
        if file_name == self.file_name_face_representations:
            self.file_embeddings = None
        elif file_name == self.file_name_faces:
            self.file_faces = None
        elif file_name == self.file_name_names:
            self.file_names = None

        query = "SELECT os_path FROM `attach` WHERE `uid` = %s AND `folder` = %s AND `filename` = %s LIMIT 1"
        data = (self.channel, self.folder, file_name)
        r = self.db.select(query, data)
        if len(r) == 0:
            logging.debug("no file " + file_name)
            return False

        path = os.path.join(self.dirImages, r[0][0])
        if not os.path.exists(path) or not os.path.isfile(path):
            logging.debug("no file " + path)
            return False
        if not os.access(path, os.W_OK):
            logging.debug("no write permission for file " + path)
            return False

        if file_name == self.file_name_face_representations:
            self.file_embeddings = path
        elif file_name == self.file_name_faces:
            self.file_faces = path
        elif file_name == self.file_name_names:
            self.file_names = path

        return True

    def check_file_by_channel(self, file_name):
        if file_name == self.file_name_faces_statistic:
            self.file_face_statistics = None
        elif file_name == self.file_name_models_statistic:
            self.file_model_statistics = None
        elif file_name == self.file_name_config:
            self.file_config = None
        elif file_name == self.file_name_config_thresholds:
            self.file_config_thresholds = None
        elif file_name == self.file_name_probe:
            self.file_probe = None
        elif file_name == self.file_name_most_similar:
            self.file_most_similar = None
        elif file_name == self.file_name_share:
            self.file_share = None

        display_path = os.path.join(self.dir_addon, file_name)
        query = "SELECT os_path FROM `attach` WHERE `uid` = %s AND `display_path` = %s LIMIT 1"
        data = (self.channel, display_path)
        r = self.db.select(query, data)
        if len(r) == 0:
            logging.debug("skipping... no file " + display_path)
            return False

        path = os.path.join(self.dirImages, r[0][0])
        if os.path.exists(path) and os.path.isfile(path) and os.access(path, os.W_OK):
            if file_name == self.file_name_faces_statistic:
                self.file_face_statistics = path
                return True
            elif file_name == self.file_name_models_statistic:
                self.file_model_statistics = path
                return True
            elif file_name == self.file_name_config:
                self.file_config = path
                return True
            elif file_name == self.file_name_config_thresholds:
                self.file_config_thresholds = path
                return True
            elif file_name == self.file_name_probe:
                self.file_probe = path
                return True
            elif file_name == self.file_name_most_similar:
                self.file_most_similar = path
                return True
            elif file_name == self.file_name_share:
                self.file_share = path
                return True
        logging.debug("no file or write permission, file " + display_path)
        return False

    def check_file_by_display_path(self, file_name, display_dir):
        if file_name == self.file_name_faces:
            self.file_faces = None
        elif file_name == self.file_name_face_representations:
            self.file_embeddings = None
        elif file_name == self.file_name_names:
            self.file_names = None

        display_path = os.path.join(display_dir, file_name)
        query = "SELECT os_path FROM `attach` WHERE `uid` = %s AND `display_path` = %s LIMIT 1"
        data = (self.channel, display_path)
        r = self.db.select(query, data)
        if len(r) == 0:
            logging.debug(" no file " + display_path)
            return False

        path = os.path.join(self.dirImages, r[0][0])
        if os.path.exists(path) and os.path.isfile(path) and os.access(path, os.W_OK):
            if file_name == self.file_name_faces:
                self.file_faces = path
                return True
            elif file_name == self.file_name_face_representations:
                self.file_embeddings = path
                return True
            elif file_name == self.file_name_names:
                self.file_names = path
                return True
        logging.debug("no file or write permission, file " + display_path)
        return False

    def get_face_representations(self):
        # Load stored face representations
        df = None  # pandas.DataFrame that holds all face representations
        if os.path.exists(self.file_embeddings):
            if os.stat(self.file_embeddings).st_size == 0:
                logging.debug("file face representations is empty yet " + self.file_embeddings)
                return df
            try :
                df = pd.read_parquet(self.file_embeddings, engine="pyarrow")
            except OSError as e:
                logging.error("Failed to read faces from file " + self.file_embeddings + ". OSError " + str(e))
                logging.error("Please make sure the script has access to this file.")
                logging.error("Stopping program. Good By...")
                sys.exit(1)
            logging.debug("loaded face representations from file " + self.file_embeddings)
        if df is not None and len(df) == 0:
            return None
        return df

    def store_face_presentations(self, df):
        if os.path.exists(self.file_embeddings) is False:
            logging.debug("face representations does not exist " + self.file_embeddings)
            return False
        df.reset_index(drop=True, inplace=True)
        try:
            df.to_parquet(self.file_embeddings, engine="pyarrow", compression='gzip')
        except OSError as e:
            logging.error("Failed to write faces to file " + self.file_embeddings + ". Exception message: " + str(e))
            logging.error("Please make sure the script has write access to this directory.")
            logging.error("Stopping program. Good By...")
            sys.exit(1)
        except BaseException as e:
            logging.error("Failed to write faces to file " + self.file_embeddings + ". Exception message: " + str(e))
            logging.error("Please make sure the script has write access to this directory.")
            logging.error("Stopping program. Good By...")
            sys.exit(1)
        logging.debug("stored face representations in file " + self.file_embeddings)
        return True

    def get_training_data(self):
        # Load stored training data
        df = None  # pandas.DataFrame that holds all face representations
        if os.path.exists(self.file_most_similar):
            if os.stat(self.file_most_similar).st_size == 0:
                logging.debug("file training data is empty yet " + str(self.file_most_similar))
                return df
            df = pd.read_parquet(self.file_most_similar, engine="pyarrow")
            logging.debug("loaded training data from file " + str(self.file_most_similar))
        if df is not None and len(df) == 0:
            return None
        return df

    def store_training_data(self, df):
        if df is None:
            logging.debug("no training data to store (dataframe is None)")
            return
        if self.has_new_names:
            
            # training data
            if self.file_most_similar is None:
                logging.debug("not storing training results because failed to read file before " + str(self.file_most_similar))
                return
            if os.path.exists(self.file_most_similar) is False:
                logging.debug("face training data does not exist " + str(self.file_most_similar))
                return
            df.reset_index(drop=True, inplace=True)
            df.to_parquet(self.file_most_similar, engine="pyarrow", compression='gzip')
            logging.debug("stored face training data in file " + str(self.file_most_similar))
            
            # faces to share
            fns = self.check_file_by_channel(self.file_name_share)
            if self.file_share is None:
                logging.debug("not storing/sharing training results because failed to read file before " + str(self.file_share))
                return
            if os.path.exists(self.file_share) is False:
                logging.debug("face training data (share) does not exist " + str(self.file_share))
                return
            # Remove faces that are not real contacts.
            # Real contacts are represented by a hash value and should be 86 char long.
            # Despite this we take min 50 chars and no blank to check wether it is a hash value.
            df = df.loc[(df['name'].str.len() > 50) & (df['name'].str.find(" ") == -1)]
            df = self.util.filter_best_shared(df, self.recognizer.max_shared)
            df.reset_index(drop=True, inplace=True)
            df.to_json(self.file_share)
        else:
            logging.debug("not storing/sharing training results because no new names where set. Files (not written): " + str(self.file_share) + " and " + str(self.file_most_similar))

    def read_shared_faces(self):
        display_path = os.path.join(self.dir_addon, self.file_pattern_shared)
        query = "SELECT os_path, display_path FROM `attach` WHERE `uid` = %s AND `display_path` LIKE %s"
        data = (self.channel, display_path)
        r = self.db.select(query, data)
        df = None
        for os_path in r:
            path = os.path.join(self.dirImages, os_path[0])
            path_displayed = os_path[1]  # used for logging only
            if os.path.exists(path) and os.path.isfile(path) and os.access(path, os.R_OK):
                df_shared = pd.read_json(path)
                if not self.recognizer.validate_shared_faces(df_shared):
                    continue
                logging.debug("read " + str(len(df_shared)) + " shared faces from file=" + path_displayed)
                if df is None:
                    df = df_shared
                else:
                    df = pd.concat([df, df_shared], ignore_index=True)
            else:
                logging.debug("no file or read permission, file pattern= " + path_displayed + ", os_path=" + path)
        return df

    def get_face_names_set_by_browser(self):
        # Load stored names
        df = None  # pandas.DataFrame that holds all face names
        if self.file_names is None:
            return df  # prior to this step a check might have failed
        # double check because the file might be deleted meanwhile
        if not os.path.exists(self.file_names):
            return df
        if os.stat(self.file_names).st_size == 0:
            logging.debug("file holding face names is empty " + self.file_names)
            return df
        df = pd.read_json(self.file_names)
        logging.debug("loaded names set/changed by user from file " + self.file_names + " full: " + self.file_names)
        return df

    def get_face_names(self):
        # Load stored names
        df = None  # pandas.DataFrame that holds all face names
        if os.path.exists(self.file_faces):
            if os.stat(self.file_faces).st_size == 0:
                logging.debug("file holding face names is empty yet " + self.file_faces)
                return df
            try:
                df = pd.read_json(self.file_faces)
            except OSError as e:
                logging.error("Failed to read names from file " + self.file_name_faces + " full:" + self.file_faces + ". Exception message: " + str(e))
            except ValueError as e:
                logging.error("Failed to read names from file " + self.file_name_faces + " full:" + self.file_faces + ". Exception message: " + str(e))
            logging.debug("loaded names from file " + self.file_name_faces + " full:" + self.file_faces)
        return df

    def init_face_names(self, df_representation):
        df_names = self.get_face_names()
        if (df_names is None) or (len(df_names) == 0):
            logging.debug("No face names yet or image(s) where delete in dir. File=" + self.file_faces)
            most_effective_method = self.util.get_most_successful_method(df_representation, False)
            df_names = self.util.filter_by_last_named(df_representation)
            df_names = self.util.number_unique_faces(df_names)
            df_names = self.util.keep_most_effectiv_method_only(df_names, most_effective_method)
            self.write_results(df_representation, df_names)
            return True
        else:
            return False

    def store_face_names(self, df):
        for column in self.columnsToIncludeAll:
            if column not in df.columns:  # for unit testing
                continue
            if column not in self.columnsToInclude:
                df = df.drop(column, axis=1)
        if 'representation' in df.columns:  # for unit testing
            df = df.drop('representation', axis=1)

        if os.path.exists(self.file_faces):
            df = df.sort_values(by=[self.sort_column], ascending=[self.sort_ascending])
            df.reset_index(drop=True, inplace=True)
            df.to_json(self.file_faces)
            try:
                df.to_json(self.file_faces)
            except OSError as e:
                logging.error("Failed to write names to file " + self.file_faces + ". Exception message: " + str(e))
                logging.error("Please make sure the script has write access to this directory.")
                logging.error("Stopping program. Good By...")
                sys.exit(1)
            except BaseException as e:
                logging.error("Failed to write names to file " + self.file_faces + ". Exception message: " + str(e))
                logging.error("Please make sure the script has write access to this directory.")
                logging.error("Stopping program. Good By...")
                sys.exit(1)
            logging.debug("stored face names in file " + self.file_faces)

    def cleanup(self):
        logging.debug("Cleaning up channel " + str(self.channel) + "...")
        imgs = self.get_images()
        images = []
        for i in imgs:
            images.append(i[0])
        df = self.get_face_representations()
        if df is not None:
            keys = self.util.remove_detector_model(df, self.remove_models, self.remove_detectors, self.folder)
            i = df[~df.file.isin(images)].index
            keys.extend(i.to_list())
            if len(keys) > 0:
                df = df.drop(keys)
                if self.store_face_presentations(df):
                    if len(i) > 0:
                        logging.info(self.folder + " " + str(len(images)) + " faces removed from face representations")

        df = self.get_face_names()
        if df is not None:
            keys = self.util.remove_detector_model(df, self.remove_models, self.remove_detectors, self.folder)
            i = df[~df.file.isin(images)].index
            keys.extend(i.to_list())
            if len(keys) > 0:
                df = df.drop(keys)
                self.store_face_names(df)
                if len(i) > 0:
                    logging.info(self.folder + " " + str(len(images)) + " faces removed from face name")

    def remove_names(self):
        if self.is_remove_names:
            df = self.get_face_representations()
            if df is not None:
                df = df.assign(name="")
                df = df.assign(name_recognized="")
                df = df.assign(time_named="")
                logging.info("removed all names from embeddings file")
                self.store_face_presentations(df)
            df = self.get_face_names()
            if df is not None:
                df = df.assign(name="")
                df = df.assign(name_recognized="")
                df = df.assign(time_named="")
                logging.info("removed all names from faces file")
                self.store_face_names(df)

    def write_statistics(self, df, most_effective_method):
        self.write_alive_signal(self.RUNNING)
        if self.check_file_by_channel(self.file_name_faces_statistic) is False:
            return
        if self.check_file_by_channel(self.file_name_models_statistic) is False:
            return

        if self.statistics:
            df = df.drop('representation', axis=1)
            df.to_csv(self.file_face_statistics)
            logging.debug("Wrote face statistics to file=" + self.file_name_faces_statistic)
            df_models = self.util.get_most_successful_method(df, True)
            name_count = len(df.name.unique()) - 1
            files = df.file.unique()
            message = str(len(files)) + " images, " + str(name_count) + " different names, minimum face width " + str(
                self.finder.min_face_width_percent) + " % of image or " + str(self.finder.min_face_width_pixel) + " px"
            row = {'model': message, 'detector': "", 'accuracy': "", 'detected': "", 'verified': "", 'recognized': "",
                   'correct': "", 'wrong': "", 'ignored': "", 'seconds': "", 'seconds/face': ""}
            df_models = pd.concat([df_models, pd.DataFrame([row])], ignore_index=True)
            df_models.to_csv(self.file_model_statistics)
            logging.debug("Wrote model statistics to file=" + self.file_name_models_statistic)
        else:
            f = open(self.file_face_statistics, 'w')
            f.write("")
            f.close()
            f = open(self.file_model_statistics, 'w')
            f.write("")
            f.close()

    def write_probe(self, results):
        if self.check_file_by_channel(self.file_name_probe) is False:
            return
        df = pd.DataFrame.from_dict(results)
        df.to_csv(self.file_probe)

    def set_time_to_wait(self, seconds):
        self.timeToWait = seconds

    def write_alive_signal(self, status):
        self.check_pid()
        elapsed_time = time.time() - self.timeLastAliveSignal
        if elapsed_time < self.timeToWait and status != self.FINISHED:
            return
        self.write_status(status)
        self.timeLastAliveSignal = time.time()

    def write_status(self, status):
        ram = self.util.get_ram()
        if ram > self.ram_allowed:
            status = self.FINISHED
        now = datetime.datetime.utcnow()
        content = status + " " + now.strftime('%Y-%m-%d %H:%M:%S') + " pid " + self.proc_id + " ram " + str(ram)
        query = "UPDATE config SET v = %s WHERE cat = %s AND k = %s"
        data = (content, 'faces', 'status' + self.status_suffix)
        self.db.update(query, data)
        logging.debug("lock status written to db:  " + content)
        if ram > self.ram_allowed:
            logging.critical("ram exceeded  " + str(self.ram_allowed) + "% , stopping program...")
            self.stop()

    def check_pid(self):
        query = "SELECT v FROM config WHERE cat = %s AND k = %s"
        data = ('faces', "status" + self.status_suffix)
        rows = self.db.select(query, data)
        if len(rows) != 1:
            logging.critical("Found " + str(len(rows)) + " results for status in database")
            self.stop()
        status = rows[0][0]
        splittees = status.split()
        if splittees[3] != "pid" or len(splittees) < 5:
            logging.critical("4th and 5th element is no pid. Found status:  " + status)
            self.stop()
        if splittees[4] != self.proc_id:
            logging.critical("Found wrong pid: own=" + self.proc_id + ", found= " + splittees[4])
            self.stop()

    def stop(self):
        self.db.close()
        logging.error("Stopping program. Good By...")
        sys.exit()

    def write_exif_dates(self, df):
        if not self.exiftool:
            return df
        exif_date = ""
        files = df.file.unique()
        for file in files:
            exif_dates = df.loc[(df['file'] == file) & (df['exif_date'] != ""), "exif_date"]
            if len(exif_dates) > 0:
                exif_date = exif_dates.values[0]
                logging.debug("exif date exists '" + exif_date + "' for image=" + file)
            else:
                query = ("SELECT os_path "
                         "FROM `attach` "
                         "WHERE `uid` = %s AND "
                         "`folder` = %s AND "
                         "`is_photo` = '1' AND "
                         "`display_path` = %s")
                data = (self.channel, self.folder, file)
                os_path = self.db.select(query, data)
                if len(os_path) == 0:
                    logging.debug("skipping... no file " + file)
                    continue
                path = os.path.join(self.dirImages, os_path[0][0])
                exif_date = self.exiftool.getDate(path)
                logging.debug("exif date = '" + str(exif_date) + "' for image=" + file)
            if exif_date != "":
                df.loc[(df['file'] == file) & (df['exif_date'] == ""), "exif_date"] = exif_date
            continue
        return df
