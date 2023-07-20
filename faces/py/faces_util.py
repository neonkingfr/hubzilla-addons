import pandas as pd
import time
import logging
import os


class Util:

    def __init__(self):
        self.IGNORE = "-ignore-"
        self.is_css_position = True

    def keep_most_effectiv_method_only(self, df, most_effective_method):
        most_effective_method = most_effective_method.sort_values(by=["accuracy"], ascending=[False])
        df_result = pd.DataFrame(columns=df.columns)
        files = df.file.unique()
        for file in files:
            df_temp = df[df['file'] == file]
            numbers = df_temp.face_nr.unique()
            for number in numbers:
                matches = df_temp.loc[
                    (df_temp['pixel'] > 0) & (df_temp['file'] == file) & (df_temp['face_nr'] == number)]
                count = len(matches)
                if count == 1:
                    df_result = pd.concat([df_result, matches], ignore_index=True)
                elif count > 1:
                    if len(most_effective_method) == 0:
                        df_result = pd.concat([df_result, matches.iloc[[0]]], ignore_index=True)
                        continue
                    keys = matches.index
                    # TODO: there must be a better approach
                    found = False
                    for row in most_effective_method.itertuples():
                        model = row.model
                        detector = row.detector
                        i = matches[(matches["model"] == model) & (matches["detector"] == detector)].index
                        if len(i) == 0:
                            continue
                        elif len(i) == 1:
                            df_result = pd.concat([df_result, df.loc[[i[0]]]], ignore_index=True)
                            found = True
                            break
                        else:
                            # This happened while testing stuff - the two files for names and representations where
                            # not in sync (after "cleanup" that was interrupted)
                            logging.error(
                                "Double face for file='" + file + "',  model='" + model + "', detector='" + detector +
                                "'. May be the 'cleanup' was interrupted after deleting pictures?")
                            df_result = pd.concat([df_result, df.loc[[i[0]]]], ignore_index=True)
                            found = True
                            break
                    if not found:
                        df_result = pd.concat([df_result, df.loc[[matches.index[0]]]], ignore_index=True)
        return df_result

    def remove_detector_model(self, df, models_to_remove, remove_detectors, dir):
        keys = []
        if (models_to_remove != "") and (remove_detectors != ""):
            if "detector" in df.columns and "model" in df.columns:
                for model in models_to_remove.split(","):
                    for detector in remove_detectors.split(","):
                        i = df.loc[(df['detector'] == detector) & (df['model'] == model)].index
                        keys.extend(i.to_list())
                logging.info("directory " + dir + " - removing " + str(
                    len(keys)) + " faces for detector=" + remove_detectors + " and model=" + models_to_remove)
        elif remove_detectors != "":
            if "detector" in df.columns:
                for detector in remove_detectors.split(","):
                    i = df.loc[df['detector'] == detector].index
                    keys.extend(i.to_list())
                logging.info(
                    "directory " + dir + " - removing " + str(
                        len(keys)) + " faces for detectors=" + remove_detectors)
        elif models_to_remove != "":
            if "model" in df.columns:
                for model in models_to_remove.split(","):
                    i = df.loc[df['model'] == model].index
                    keys.extend(i.to_list())
                logging.info(
                    "directory " + dir + " - removing " + str(len(keys)) + " faces for model=" + models_to_remove)
        return keys

    def copy_name_to_same_faces(self, df):
        logging.debug("Start copy names to same faces")
        start_sort = time.time()
        df = df.sort_values(["file", "face_nr", "time_named"], ascending=[True, True, False])
        sort_time = str(time.time() - start_sort)
        start_copy = time.time()
        file_last = ""
        face_nr_last = ""
        name = ""
        time_named = ""
        for row in df.itertuples():
            file = row.file
            face_nr = row.face_nr
            if file != file_last:
                face_nr_last = face_nr
                file_last = file
                name = row.name
                time_named = row.time_named
                continue
            if face_nr != face_nr_last:
                face_nr_last = face_nr
                name = row.name
                time_named = row.time_named
                continue
            df.loc[row.Index, ['name', 'time_named']] = [name, time_named]
            # df.loc[row.Index, ['name']] = [name]
        copy_time = str(time.time() - start_copy)
        logging.debug("Finished copy names to same faces, copy time=" + copy_time + " s, sort time=" + sort_time + "s")
        return df

    def filter_by_last_named(self, df):
        logging.debug("filter last named faces")
        df = df.sort_values(["file", "face_nr", "time_named"], ascending=[True, True, False])
        file_last = ""
        face_nr_last = ""
        name = ""
        index_row_temp = 0  # this is for a very special case see below
        time_named = ""
        keys = []
        ignore = False
        for row in df.itertuples():
            file = row.file
            face_nr = row.face_nr
            if file != file_last:
                face_nr_last = face_nr
                file_last = file
                name = row.name
                time_named = row.time_named
                if time_named == "":
                    ignore = True
                else:
                    ignore = False
                    index_row_temp = row.Index
                continue
            if face_nr != face_nr_last:
                face_nr_last = face_nr
                name = row.name
                time_named = row.time_named
                if time_named == "":
                    ignore = True
                else:
                    ignore = False
                    index_row_temp = row.Index
                continue
            if not ignore:
                if name == "" and row.name != "":
                    # This should be very rare case.
                    # But it has happened when using shared faces.
                    # Not sure if the shared faces realy was the cause.
                    keys.append(index_row_temp)
                    name = row.name
                else:
                    keys.append(row.Index)
        if len(keys) > 0:
            df = df.drop(keys)
            logging.debug("removed " + str(len(keys)) + " faces.")
        return df

    # TODO: In praxis this function should be obsolete.
    # Why?
    # The keepMostEffectivMethodOnly() is applied before and this has done the job already.
    # After keepMostEffectivMethodOnly() only one face should be present at a certain position in the images.
    def minimize_results(self, df, remove_method):
        no_faces = df.loc[(df['face_nr'] == 0)]
        if len(no_faces) > 0:
            keys = no_faces.index
            df = df.drop(keys)
        paths = df.file.unique()
        for path in paths:
            image = df[df['file'] == path]
            unique_face_numbers = image.face_nr.unique()
            for nr in unique_face_numbers:  # faces at the same position in image
                if nr == 0:
                    continue  # indicates an image without a face
                faces = image.loc[image["face_nr"] == nr]
                if len(faces) == 1:
                    continue
                names = faces.name.unique()
                names_recognized = faces.name_recognized.unique()
                if len(names) > 0 and names[0] != "":
                    # keep one single face for every name (it should exist exactly one single name always)
                    if len(names) > 1:
                        logging.warning(
                            "This should never happen and is a programming error. More than one names (set by user) "
                            "where found in file=" + str(path) + " for face number=" + str(nr) +
                            ". The program will use the first name only.")
                    faces = df.loc[(df['face_nr'] == nr) & (df['file'] == path)]
                    keys = faces.index
                    if remove_method:
                        df.loc[keys[0], ["model", "detector", "name_recognized"]] = ["", "", ""]
                    keys = keys[1:]
                    df = df.drop(keys)
                elif len(names_recognized) > 0:
                    # keep one single face for every recognized name
                    for name in names_recognized:
                        faces = df.loc[(df['face_nr'] == nr) & (df['file'] == path) & (df['name_recognized'] == name)]
                        keys = faces.index
                        if name != "":
                            if remove_method:
                                df.loc[keys[0], ["model", "detector"]] = ["", ""]
                            keys = keys[1:]
                            df = df.drop(keys)
                        elif name == "" and len(names_recognized) > 1:
                            df = df.drop(keys)
                        elif name == "" and len(names_recognized) == 1:
                            if remove_method:
                                df.loc[keys[0], ["model", "detector"]] = ["", ""]
                            keys = keys[1:]
                            df = df.drop(keys)
        return df

    def number_unique_faces(self, df):
        tic = time.time()
        # order by "time_created" keeps same order (better) over time if several detectors are used.
        # This is important for naming faces (by PHP)
        df = df.sort_values(by=["file"])
        file = ""
        faces = []
        nr = 0
        for row in df.itertuples():
            if file != row.file:
                file = row.file
                faces = []
                nr = 0
            if len(row.position) != 4:
                continue
            if len(faces) == 0:
                nr += 1
                faces.append({'nr': nr, 'position': row.position})
                df.loc[row.Index, "face_nr"] = nr
                continue
            found = False
            for face in faces:
                if self.is_same_face(face["position"], row.position):
                    df.loc[row.Index, "face_nr"] = face['nr']
                    found = True
                    break
            if not found:
                nr += 1
                faces.append({'nr': nr, 'position': row.position})
                df.loc[row.Index, "face_nr"] = nr
        logging.debug("number unique faces took " + str(time.time() - tic) + " seconds for " + str(len(df)) + " files")
        return df

    def is_same_face(self, face_a, face_b):
        if self.is_css_position:
            # margins left, right, top, bottom in percent
            middle_of_face_x = int(face_a[0]) + (100 - (int(face_a[1]) + int(face_a[0]))) / 2
            middle_of_face_y = int(face_a[2]) + (100 - (int(face_a[2]) + int(face_a[3]))) / 2
            end_of_face_b_x = 100 - face_b[1]
            end_of_face_b_y = 100 - face_b[3]
            # is middle of face inside face_b position?
            if (face_b[0] < middle_of_face_x) and (middle_of_face_x < (end_of_face_b_x)):
                if (face_b[2] < middle_of_face_y) and (middle_of_face_y < (end_of_face_b_y)):
                    middle_of_face_b_x = int(face_b[0]) + (100 - (int(face_b[1]) + int(face_b[0]))) / 2
                    middle_of_face_b_y = int(face_b[2]) + (100 - (int(face_b[2]) + int(face_b[3]))) / 2
                    end_of_face_x = 100 - face_a[1]
                    end_of_face_y = 100 - face_a[3]
                    # is middle of face_b position inside face ?
                    if (face_a[0] < middle_of_face_b_x) and (middle_of_face_b_x < (end_of_face_x)):
                        if (face_a[2] < middle_of_face_b_y) and (middle_of_face_b_y < (end_of_face_y)):
                            return True
        else:
            # x, y, w, h : left upper corner is x=0, y=0
            middle_of_face_x = int(face_a[0]) + int(face_a[2]) / 2
            middle_of_face_y = int(face_a[1]) + int(face_a[3]) / 2
            end_of_face_b_x = face_b[0] + face_b[2]
            end_of_face_b_y = face_b[1] + face_b[3]
            # is middle of face b inside face a?
            if (face_b[0] < middle_of_face_x) and (middle_of_face_x < (end_of_face_b_x)):
                if (face_b[1] < middle_of_face_y) and (middle_of_face_y < (end_of_face_b_y)):
                    middle_of_face_b_x = int(face_b[0]) + int(face_b[2]) / 2
                    middle_of_face_b_y = int(face_b[1]) + int(face_b[3]) / 2
                    end_of_face_x = face_a[0] + face_a[2]
                    end_of_face_y = face_a[1] + face_a[3]
                    # is middle of face a inside face b?
                    if (face_a[0] < middle_of_face_b_x) and (middle_of_face_b_x < (end_of_face_x)):
                        if (face_a[1] < middle_of_face_b_y) and (middle_of_face_b_y < (end_of_face_y)):
                            return True
        return False

    def get_most_successful_method(self, df, write_detector):
        df_results = df.loc[
            df['time_named'] != "", ['name', 'name_recognized', 'model', 'detector', 'duration_representation',
                                     'duration_detection']]

        df_most_effective = pd.DataFrame(
            columns=['model', 'detector', 'accuracy', 'detected', 'recognized', 'set', 'correct', 'wrong',
                     'ignored', "seconds", 'seconds/face'])
        models = df_results.model.unique()
        detectors = df_results.detector.unique()
        for detector in detectors:
            sum_correct = 0
            sum_wrong = 0
            sum_ignored = 0
            sum_detected = 0
            sum_recognized = 0
            for model in models:
                correct = df_results.loc[(df_results['model'] == model) &
                                         (df_results['detector'] == detector) &
                                         (df_results['name'] != "") &
                                         (df_results['name'] == df_results['name_recognized'])]
                sum_correct += len(correct)
                wrong = df_results.loc[(df_results['model'] == model) &
                                       (df_results['detector'] == detector) &
                                       (df_results['name'] != df_results['name_recognized']) &
                                       (df_results['name_recognized'] != "")]
                sum_wrong += len(wrong)
                ignored = df_results.loc[(df_results['model'] == model) &
                                         (df_results['detector'] == detector) &
                                         (df_results['name'] == self.IGNORE)]
                sum_ignored += len(ignored)
                df_all = df_results.loc[(df_results['model'] == model) &
                                        (df_results['detector'] == detector), 'duration_representation']
                if len(df_all) == 0:
                    continue
                detected = df.loc[(df['model'] == model) & (df['detector'] == detector) & (df['pixel'] > 0)]
                recognized = df.loc[(df['model'] == model) &
                                    (df['detector'] == detector) &
                                    (df['pixel'] > 0) &
                                    (df['name_recognized'] != "")]
                sum_detected += len(detected)
                sum_recognized += len(recognized)
                accuracy = round(len(correct) * 100 / len(df_all), 1)
                row = {'model': model, 'detector': detector, 'accuracy': accuracy, 'detected': len(detected),
                       'set': len(df_all), 'recognized': len(recognized), 'correct': len(correct),
                       'wrong': len(wrong), 'ignored': len(ignored), 'seconds': round(sum(df_all.values)),
                       'seconds/face': round(sum(df_all.values) / len(df_all), 2)}
                df_most_effective = pd.concat([df_most_effective, pd.DataFrame([row])], ignore_index=True)
            if write_detector:
                df_all = df_results.loc[df_results['detector'] == detector, 'duration_representation']
                if len(df_all) == 0:
                    continue
                accuracy = round(sum_correct * 100 / len(df_all), 1)
                row = {'model': "", 'detector': detector, 'accuracy': accuracy, 'detected': sum_detected,
                       'set': len(df_all), 'recognized': sum_recognized, 'correct': sum_correct,
                       'wrong': sum_wrong, 'ignored': sum_ignored, 'seconds': round(sum(df_all.values)),
                       'seconds/face': round(sum(df_all.values) / len(df_all), 2)}
                df_most_effective = pd.concat([df_most_effective, pd.DataFrame([row])], ignore_index=True)

        df_most_effective = df_most_effective.sort_values(by=["accuracy"], ascending=[False])
        logging.debug("Tabel of effectiveness of detectors and models was created")
        return df_most_effective

    def has_any_name_changed(self, df, df_names):
        files = df.file.unique()
        for file in files:
            faces = df[df['file'] == file]
            face_numbers = faces.face_nr.unique()
            for number in face_numbers:
                face_a = df[(df['file'] == file) & (df['face_nr'] == number)]
                faces_b = df_names[df_names['file'] == file]
                if len(faces_b) == 0:
                    return True
                for face_b in faces_b.itertuples():
                    position_b = face_b.position
                    if self.is_same_face(face_a.iloc[0, 2], position_b):
                        if face_a.name.values[0] != face_b.name or \
                                face_a.name_recognized.values[0] != face_b.name_recognized:
                            return True
                        elif face_b.time_named != face_a.time_named.values[0]:
                            return True
                            
        return False

    def remove_other_than_recognized(self, faces, df):
        for face in faces:
            face_id = face["id"]
            face = df.loc[df['id'] == face_id, ["file", "face_nr"]]
            if len(face) == 0:
                continue  # has different detector and was removed in loop before
            file = face["file"].item()
            face_nr = face["face_nr"].item()
            f = df.loc[(df['file'] == file) & (df['face_nr'] == face_nr)]
            keys = f.index
            if len(keys) == 1:
                return df
            df = df.drop(keys[1:])
        return df

    def create_frame_embeddings(self):
        df = pd.DataFrame({'id': pd.Series(dtype='str'),
                           'file': pd.Series(dtype='str'),
                           'position': pd.Series(dtype='int'),
                           'pixel': pd.Series(dtype='int'),  # px
                           'percent': pd.Series(dtype='int'),
                           'face_nr': pd.Series(dtype='int'),
                           'name': pd.Series(dtype='str'),
                           'name_recognized': pd.Series(dtype='str'),
                           'time_named': pd.Series(dtype='str'),
                           'detector': pd.Series(dtype='str'),
                           'model': pd.Series(dtype='str'),
                           'duration_detection': pd.Series(dtype='float'),
                           'duration_representation': pd.Series(dtype='float'),
                           'time_created': pd.Series(dtype='datetime64[ns]'),
                           'representation': pd.Series(dtype='float'),
                           'distance': pd.Series(dtype='float'),
                           'distance_metric': pd.Series(dtype='str'),
                           'duration_recognized': pd.Series(dtype='float'),
                           'directory': pd.Series(dtype='str'),
                           'exif_date': pd.Series(dtype='str'),
                           'mtime': pd.Series(dtype='str'),
                           'emotions': pd.Series(dtype='str'),
                           'emotion': pd.Series(dtype='str'),
                           'age': pd.Series(dtype='int'),
                           'gender': pd.Series(dtype='str'),
                           'gender_prediction': pd.Series(dtype='float'),
                           'races': pd.Series(dtype='str'),
                           'race': pd.Series(dtype='str')})
        return df

    def add_row_embedding(self, df, values):
        if df is None:
            df = self.create_frame_embeddings()

        row = pd.Series(
            [values[0],
             values[1],
             values[2],
             values[3],
             values[4],
             values[5],
             values[6],
             values[7],
             values[8],
             values[9],
             values[10],
             values[11],
             values[12],
             values[13],
             values[14],
             values[15],
             values[16],
             values[17],
             values[18],
             values[19],
             values[20],
             values[21]], index=df.columns)

        df = pd.concat([df, pd.DataFrame([row])], ignore_index=True)
        return df

    def create_frame_names(self):
        df = pd.DataFrame({'id': pd.Series(dtype='str'),
                           'name': pd.Series(dtype='str'),
                           'time_named': pd.Series(dtype='str')})
        return df

    def check_ram(self, max):
        ram_percent = self.get_ram()
        if ram_percent > max:
            logging.critical("RAM memory exceeded " + str(max) + "%")
            return False
        return True

    def get_ram(self):
        total_memory, used_memory, free_memory = map(int, os.popen('free -t -m').readlines()[-1].split()[1:])
        ram_percent = int(round((used_memory / total_memory) * 100, 2))
        logging.debug("RAM memory used: " + str(ram_percent) + "%")
        return ram_percent

    def prepare_probe(self, df, model):
        df.loc[(df['model'] == model), ['name_recognized', 'distance']] = ["", ""]
        return df

    def analyse_probe(self, df, faces, detector, model, threshold, probe_results, probe_cols):
        for face in faces:
            face_id = face['id']
            df.loc[df['id'] == face_id, ['name_recognized', 'distance']] = [face['name_recognized'], face['distance']]

        dirs = df.directory.unique()
        names = []
        for d in dirs:
            if not d.startswith("faces/probe"):
                continue
            name = d[d.rfind("/") + 1:]
            if name != "unknown":
                names.append(name)

        if len(names) == 0:
            return probe_results

        metric = next(iter(threshold))

        if len(probe_cols) == 0:
            probe_cols = ["detector", "model", "metric", "distance", "min-max-average"]

        result = {}
        min_max_average = []

        result["detector"] = detector
        result["model"] = model
        result["metric"] = metric
        result["distance"] = round(threshold[metric], 4)
        for name in names:
            df_name = df.loc[
                (df['model'] == model) &
                (df['detector'] == detector) &
                (df['name_recognized'] == name), ['distance', 'directory']]
            df_name_positives = df_name.loc[(df_name['directory'] == "faces/probe/" + name)]
            positives = len(df_name_positives)
            distances = df_name_positives["distance"].values  # take no distances from directory unknown
            details = {}
            if len(distances) > 0:
                details[name] = [round(min(distances), 4), round(max(distances), 4),
                                 round(sum(distances) / len(distances), 4)]
            min_max_average.append(details)
            df_name_false_positives = df_name.loc[(df_name['directory'] == "faces/probe/unknown")]
            false_positives = len(df_name_false_positives)
            result[name] = positives
            result[name + "-in-unknown"] = false_positives
            if name not in probe_cols:
                probe_cols.append(name)
                probe_cols.append(name + "-in-unknown")
        result["min-max-average"] = min_max_average
        probe_results.append(result)

        return [probe_results, probe_cols]

    def build_probe_results(self, probe_results, probe_cols):
        result = []

        for r in probe_results:
            row = {}
            for col in probe_cols:
                row[col] = r[col]
            result.append(row)
        return result
    
    def filter_best_shared(self, df, max_shared):
        tic = time.time()
        keys = []
        models = df.model.unique()
        for model in models:
            detectors = df.detector.unique()
            for detector in detectors:
                df_model_detector = df.loc[ (df['model'] == model) & (df['detector'] == detector)]  
                if len(df_model_detector) == 0:
                    logging.debug(model + " " + detector + " - No results for this faces")
                    continue      
                names = df_model_detector.name.unique()
                # leave those embeddings for each person that have the nearest average distance to the same person
                for name in names:
                    faces = df_model_detector[df_model_detector['name'] == name]
                    # take the best as defined in percent and max number
                    faces = faces.sort_values(by=["average_distance"])
                    l = len(faces)
                    if l > max_shared:
                        l = max_shared
                    i = 0
                    for index, face in faces.iterrows():
                        if i >= l:
                            keys.append(index)
                        i = 1 + i
                    logging.debug(model + " " + detector + " - left " + str(l) + " faces for " + name)
        df = df.drop(keys)
        logging.debug("filtering best shared faces finished - left "
                    + str(len(df)) + " faces to share, max shared faces =" + str(max_shared)
                    + " in " + str(round(time.time() - tic, 5)) + " s")
        return df
