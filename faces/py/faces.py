import faces_worker
import faces_db
import argparse
import sys
import distutils
import logging

parser = argparse.ArgumentParser()
parser.add_argument("--host", help="db host url")
parser.add_argument("--user", help="db user")
parser.add_argument("--pass", help="db pass")
parser.add_argument("--db", help="db")
parser.add_argument("--imagespath", help="absolute path to image dir")
parser.add_argument("--channelid", help="channel id, default is 0")
parser.add_argument("--loglevel")
parser.add_argument("--logfile")
parser.add_argument("--procid")
parser.add_argument("--recognize")
parser.add_argument("--probe")
parser.add_argument("--rm_detectors")
parser.add_argument("--rm_models")
parser.add_argument("--rm_names")

args = vars(parser.parse_args())

# +++++++++++++
# start logger
# +++++++++++++
frm = logging.Formatter("{asctime} {levelname} {process} {filename} {lineno}: {message}",
                        style="{")
logger = logging.getLogger()
log_file = args["logfile"]
print("param logfile=" + log_file)
if (log_file is not None) and (log_file != ""):
    handler_file = logging.FileHandler(log_file, "w")
    handler_file.setFormatter(frm)
    logger.addHandler(handler_file)
    print("yes, logger is configured to write to file")

loglevel = int(args["loglevel"])
print("param loglevel=" + str(loglevel))
""" 
values from PHP...
LOGGER_NORMAL 0
LOGGER_TRACE 1
LOGGER_DEBUG 2
LOGGER_DATA 3
LOGGER_ALL 4
"""
if loglevel:
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

logData = False
if loglevel >= 4:
    logData = True

# +++++++++++++++++++
# load db connection
# +++++++++++++++++++
logging.debug("db host = " + args["host"])
logging.debug("db user = " + args["user"])
# logging.debug("db pass = " + args["pass"])
logging.debug("db db = " + args["db"])
db = faces_db.Database(logData)
# db.connect("hubzilla", ".", "127.0.0.1", "hubzilla")
db.connect(args["user"], args["pass"], args["host"], args["db"])

# +++++++++++++++++++
# run parameters
# +++++++++++++++++++

if args["imagespath"] is None:
    logging.error("image directory is not set, exit program...")
    sys.exit()
logging.debug("image dir = " + args["imagespath"])

if args["procid"] is None:
    logging.error("procid is not set, exit program...")
    sys.exit()
logging.debug("procid = " + args["procid"])

channel_id = 0
if args["channelid"]:
    channel_id = int(args["channelid"])
logging.debug("channel id = " + str(channel_id))

is_recognize = False
if args["recognize"]:
    is_recognize = True
logging.debug("recognize  = " + str(is_recognize))

is_probe = False
if args["probe"]:
    is_probe = True
logging.debug("probe  = " + str(is_probe))

worker = faces_worker.Worker()
if args["rm_models"]:
    worker.remove_models = args["rm_models"]
if args["rm_detectors"]:
    worker.remove_detectors = args["rm_detectors"]
if args["rm_names"]:
    worker.is_remove_names = True

logging.debug("set db in worker")
worker.set_db(db)

# +++++++++++++++++++
# run
# +++++++++++++++++++
worker.run(args["imagespath"], args["procid"], channel_id, is_recognize, is_probe)

db.close()
logging.info("OK, good by...")
