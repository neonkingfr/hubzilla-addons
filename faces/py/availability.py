import os
import sys
import argparse
import importlib

parser = argparse.ArgumentParser()
parser.add_argument("--demography")
parser.add_argument("--detectors")
parser.add_argument("--models")
args = vars(parser.parse_args())


def print_ram():
    total_memory, used_memory, free_memory = map(int, os.popen('free -t -m').readlines()[-1].split()[1:])
    print("RAM memory % used:", round((used_memory / total_memory) * 100, 2))


deepface_spec = importlib.util.find_spec("deepface")
if deepface_spec is None:
    print("deepface not found")
    exit("failed to find deepface")

models = ['Facenet512', 'ArcFace', 'VGG-Face', 'Facenet', 'OpenFace', 'DeepFace', 'SFace']
# 'DeepID' did not work reliably in tests
print_ram()

if args["models"] is not None:
    m = args["models"]
    models = m.split(",")

from deepface import DeepFace

for model in models:
    try:
        DeepFace.build_model(model)
        print("Found model " + model)
        print_ram()
    except Exception as e:
        print(str(e))

detectors = ["retinaface", "mtcnn", "ssd", "opencv", "mediapipe"]

if args["detectors"] is not None:
    d = args["detectors"]
    detectors = d.split(",")

from deepface.detectors import FaceDetector

for detector in detectors:
    try:
        FaceDetector.build_model(detector)
        print("Found detector " + detector)
        print_ram()
    except Exception as e:
        print(str(e))

demography = ["Emotion", "Age", "Gender", "Race"]

if args["demography"] is not None:
    if args["demography"] == "off":
        print("demography is switched off")
        sys.exit(0)
    d = args["demography"]
    demography = d.split(",")

for model in demography:
    try:
        DeepFace.build_model(model)
        print("Found demography " + model)
        print_ram()
    except Exception as e:
        print(str(e))
