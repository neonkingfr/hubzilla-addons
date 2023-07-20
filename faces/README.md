
State of the art face recognition

# Acknowledgements

The python scripts use other open source libraries:

- deepface, MIT License, code https://github.com/serengil/deepface
- mysql-connector-python, GNU GPLv2 (with FOSS License Exception), webpage https://pypi.org/project/mysql-connector-python/

---

# How to install

Before you try to run the face recognition on your server
make sure you installed python and some python packages.

This addon does not need python on the server if

- the face recognition runs on another computer connected via webDAV, and
- you use this addon just to...

  + view faces in images (there is a face in a picture)
  + name faces (this face is John)
  + show a person in other images (John is found in other images too)


## Python Package Manager

The following was tested under Debian 11.

    su -
    apt-get update
    apt-get -y install python3-pip
    pip --version

## Python Connection to Database

### Install

Make sure to have installed mysql-connector-python

    su -
    pip install mysql-connector-python

### Upgrade

    su -
    pip install --upgrade mysql-connector-python


## Python Module Deepface

**It is recommended to install the python package deepface for the user www-data**
(or whatever user runs your webserver). This will avoid problems when accessing python modules installed by pip.

### Preparations

Prepare directories for the installation and execution of deepface.

    su -
    mkdir /var/www/.local
    chown www-data:www-data /var/www/.local/
    mkdir /var/www/.cache
    chown www-data:www-data /var/www/.cache/
    mkdir /var/www/.deepface
    chown www-data:www-data /var/www/.deepface/
    mkdir -p /var/www/.config/matplotlib
    chown www-data:www-data /var/www/.config/matplotlib

### Install and Test
 
Open a shell for the user www-data

    su -
    sudo -u www-data sh
Install the python module deepface (as user www-data).

    whoami
    pip install deepface mediapipe fastparquet pyarrow

Test if the user www-data is able to run the python self check successfully
(provided the addons are found under "/var/www/streams/addon"...) 

    python3 /var/www/streams/addon/faces/py/availability.py

This will download the models initially to "/var/www/.deepface/".
Use the parameter "-demography off" if you do not want to load the demography models.

Watching CPU and RAM might be usefull.

Try again to see the test result only. The script should output something like

        RAM memory % used: 21.11
        Found model Facenet512
        RAM memory % used: 24.75
        Found model ArcFace
        RAM memory % used: 26.36
        Found model VGG-Face
        RAM memory % used: 31.62
        Found model Facenet
        RAM memory % used: 31.98
        Found model OpenFace
        RAM memory % used: 32.0
        Found model DeepFace
        RAM memory % used: 37.17
        Found model SFace
        RAM memory % used: 37.62
        Found detector retinaface
        RAM memory % used: 38.98
        Found detector mtcnn
        RAM memory % used: 38.98
        Found detector ssd
        RAM memory % used: 39.03
        Found detector mediapipe
        RAM memory % used: 38.99
        Found detector opencv
        RAM memory % used: 38.99
        Found demography Emotion
        RAM memory % used: 39.01
        Found demography Age
        RAM memory % used: 44.15
        Found demography Gender
        RAM memory % used: 49.52
        Found demography Race
        RAM memory % used: 54.95

You can exit the shell for www-data

    exit

### Update / Upgrade
 
Open a shell for the user www-data

    su -
    sudo -u www-data sh
    whoami
Upgrade the python module deepface (as user www-data).

    pip install --upgrade deepface mediapipe fastparquet pyarrow
Or upgrade to the latest possible versions

    pip install --upgrade --upgrade-strategy eager deepface mediapipe fastparquet pyarrow

---

# How to configure as Admin

The [settings page](admin/addons/faces) includes a self check and shows the installed versions. 
(That's why it takes so long to load.)

## How to enable Logging

The python scripts will log to a file name "faces.log".

1. To set the **log directory**: Activate the addon logrot under [addons](admin/addons/) and set a directory in the settings of the addon [logrot](admin/addons/logrot)
2. **Enable debugging** and set the **log level** under [admin/logs/](/admin/logs/)

The log file will be overwritten every time the python scripts start.

# How to use

Provided you uploaded some images to your channel...

1. Install the app [here](/apps/available)
2. Open the app. Result:
  * The face detection starts in the background
  * **BE PATIENT** if you use the addon for the **very first time**. Reload the addon page after some time 
3. You will see frames around faces. Click on a frame and 
  * give the face a name, or
  * The face recognition will start to guess the name in other faces.
  * Confirm guessed names to improve the recognition.

## Permissions

View faces: Everybody who has the permission to view your cloud files.

Write faces: Everybody who has the permission to write your cloud files.

## Deletion

### Admin - for all Users

The admin can delete the encodings and names for all users unter [admin/addons/faces/](/admin/addons/faces/).
This will delete all files containing data the addon created.

- faces.json in every directory (face locations and names)
- faces.gzip in every directory (faces encodings)
- faces_statistics.json if present
- models_statistics.json if present

### User

Method 1

A user can delete his face encodings and names by appending "/remove" at
the end of the URL and follow the instruction there. Example:

    https://my-domain.org/faces/my-nick-name/remove

Method 2

Manually delete in files

    https://my-domain.org/cloud/my-nick-name/

- faces.json in every directory (face locations and names)
- faces.gzip in every directory (faces encodings)
- faces_statistics.json if present
- models_statistics.json if present

# FAQs

## How does a Face Recognition work - basically?

Three steps are involved

1. The **detection** process will find a face and it's position.
   Available detectors are opencv, ssd, mtcnn, retinaface, mediapipe
2. The **recognition** process will create an encoding for each face, basically a vector.
   Available models are VGG-Face, Facenet, Facenet512, ArcFace, OpenFace, DeepFace
3. The **comparation** of encodings (faces) is done by comparing distances of vectors.
   Available distance metrics are cosine, euclidean, euclidean_l2.
   

## When is the face detection and recognition started?

It is started every time you

- load the addon page: This starts the whole process.
- set a name for a face: This starts seaching faces in other images only

Parallel running processes on the server are prevented.

## Is it possible to tag names without having Python installed?

Yes, if...

- you have clones. At least one clone must have installed Python and and the Python module deepface to
detect and regognize faces. The files containing the face encodings and names are synchronized between
the channel clones. Once a face is detected and the encodings created there is no
need for the Python scripts, except that they will guess the names for other faces.
- you connect your computer via webdav and run the face detection/recognition from there.

## Is there more information about the detectors and models?

Models

[SFace](https://deepai.org/publication/sface-sigmoid-constrained-hypersphere-loss-for-robust-face-recognition) : Sigmoid-Constrained Hypersphere Loss for Robust Face Recognition

# TODOs / Ideas

If you are tagged by others in their images (you = your channel) :

- You will get a notification who has tagged you and and a link to the images.
- You can view the images and remove yourself.
  * In case you do not have the permission to view an image you will see a delete-button instead.
  * The removal of a tag can't be undone by the owner of the image. (The face is not "taggable" anymore.)

# Privacy

If you run your own server:

- Your faces and names will not leave your server.

If you do not use your own server:

- Activate/deactivate the face recognition yourself, [Art. 7 GDPR](https://gdpr-info.eu/art-7-gdpr/). (There is no server wide face recognition.)
- Allow or deny users/groups to view and edit your faces and names, [Art. 25 GDPR](https://gdpr-info.eu/art-25-gdpr/).
- Export your faces and names, [Art. 20 GDPR](https://gdpr-info.eu/art-20-gdpr/). Visit [this](uexport) page
- Import your faces and names at a different provider, [Art. 20 GDPR](https://gdpr-info.eu/art-20-gdpr/). See the remarks on [this](uexport) page
- Faces and names are synchronized automatically to your channel clones and are kept in sync, [Art. 20 GDPR](https://gdpr-info.eu/art-20-gdpr/).
- Correct you faces and names at any time, [Art. 16 GDPR](https://gdpr-info.eu/art-16-gdpr/).
- Delete your faces and names at any time, [Art. 17 GDPR](https://gdpr-info.eu/art-17-gdpr/), [Art. 21 GDPR](https://gdpr-info.eu/art-21-gdpr/). Append "/remove" to the URL of the addon, example: https://my-domain.org/faces/my-nick-name/remove

Keep in mind: no image, no face detection. The crucial step is the upload of an image.
