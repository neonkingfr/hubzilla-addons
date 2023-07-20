import worker
import argparse
import logging
import os

parser = argparse.ArgumentParser()
parser.add_argument("--directory", "-d", help="absolute path to image dir")
parser.add_argument("--loglevel", help="1 = INFO, 2 = DEBUG, 3 = DATA, 4 = ALL")
parser.add_argument("--logfile", help="path to log file")
parser.add_argument("--recognize", action='store_true', help="Do not detect faces. Compare faces only. (just a flag argument)")
parser.add_argument("--probe", action='store_true', help="experimental feature to find the best configuration (just a flag argument)")
parser.add_argument("--rm_detectors", "-rd", help="delete results for detectors, example 'opencv,ssd'")
parser.add_argument("--rm_models", "-rm", help="delete results for models, example 'ArcFace,OpenFace'")
parser.add_argument("--rm_names", action='store_true', help="remove all name set by the user (just a flag argument)")
parser.add_argument("--exclude", "-x", help="directories to exclude, example '.lost+found,.Trash-1000'")

args = vars(parser.parse_args())

# +++++++++++++
# start logger
# +++++++++++++
frm = logging.Formatter("{asctime} {levelname} {process} {filename} {lineno}: {message}", style="{")
logger = logging.getLogger()
log_file = args["logfile"]
if (log_file is not None) and (log_file != ""):
    print("param logfile=" + log_file)
    handler_file = logging.FileHandler(log_file, "w")
    handler_file.setFormatter(frm)
    logger.addHandler(handler_file)
    print("yes, logger is configured to write to file")

""" 
values from PHP...
LOGGER_NORMAL 0
LOGGER_TRACE 1
LOGGER_DEBUG 2
LOGGER_DATA 3
LOGGER_ALL 4
"""
if args["loglevel"] is not None:
    loglevel = int(args["loglevel"])
    print("param loglevel=" + str(loglevel))
    if loglevel < 0:
        logger.setLevel(logging.NOTSET)
    elif loglevel >= 2:
        logger.setLevel(logging.DEBUG)
    elif loglevel >= 0:
        logger.setLevel(logging.INFO)
    print("yes, log level is configured")
else:
    logger.setLevel(logging.INFO)
logging.debug("started logging")

# +++++++++++++++++++
# run parameters
# +++++++++++++++++++

imgdir = args["directory"]
if imgdir is None:
    imgdir = os.getcwd()
    logging.info("Missing parameter --dir ? Using current directory " + imgdir + " to find pictures")
logging.info("image directory = " + imgdir)

is_recognize = args["recognize"]
logging.debug("recognize  = " + str(is_recognize))

is_probe = args["probe"]
logging.debug("probe  = " + str(is_probe))


worker = worker.Worker()
if args["rm_models"]:
    worker.remove_models = args["rm_models"]
if args["rm_detectors"]:
    worker.remove_detectors = args["rm_detectors"]
worker.is_remove_names = args["rm_names"]
if args["exclude"]:
    worker.set_exclude_directories(args["exclude"])

# +++++++++++++++++++
# run
# +++++++++++++++++++
worker.run(imgdir, is_recognize, is_probe)

logging.info("OK, good by...")
