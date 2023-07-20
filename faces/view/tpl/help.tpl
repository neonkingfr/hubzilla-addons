<h1>Reference</h1>
<p>
    Addon faces version {{$version}}.
</p>

<h2>Using the Addon for the very first Time</h2>

<p>
    Open <a class='link_correction' href="faces/channel-nick/">here</a>.
</p>
<p>
    Result: The face detection starts to scans all image in your
    <a class='link_correction' href="photos/channel-nick">photo album</a>.
</p>
<p>
    Be patient. This can take minutes to hours.
    The page will stay empty until the first detection has finished.
</p>
<p>
    After some time detected faces show a frame.
</p>
<p>
    Click into a frame. A dialog will pop-up.
</p>
<p>
    Give a name
</p>
<ul>
    <li>
        type any name like Brigitte Bardot, or
    </li>
    <li>
        choose one of your contacts from the list
    </li>
</ul>
<p>
    Confirm by pressing enter or
    <button class="btn" id="face-edit-set-name"> <i class="fa fa-thumbs-up fa-2x"></i></button>.
</p>

<h2>What you can do with detected faces</h2>

<p>
    <button class="btn" id="face-edit-set-name"> <i class="fa fa-thumbs-up fa-2x"></i></button>
    Confirm the name. The face will be used to find the same person in other images.
</p>
<p>
    <button class="btn" id="face-edit-set-unknown"> <i class="fa fa-question fa-2x"></i></button>
    This is person you don't know. This face will not be matched with known
    faces anymore but you still will see a grey dotted frame around the face and will be able
    to set a name later on.
</p>
<p>
    <button class="btn" id="face-edit-set-ignore"> <i class="fa fa-eye-slash fa-2x"></i></button>
    This is no face at all. Tell the face recognition to ignore this. You will
    never see this face again.
</p>


<h2>Appearance</h2>

<h3><button class="btn" id="button-faces-filter"><i class="fa fa-filter fa-2x"></i></button> Filter Pictures</h3>
<p>
    <strong>Name</strong>: Choose one or more names from the list.
</p>
<p>
    <strong>AND</strong> search: Find pictures only where "Jane" AND "Bob" are together in 
    a picture.
</p>
<p>
    <strong>Start date</strong> and <strong>end date</strong> as...
<ul>
    <li>
        <strong>upload</strong> date of the picture (default)
    </li>
    <li>
        <strong>exif date</strong> the date when the picture was taken
    </li>
</ul>Use the
<a class='link_correction' href="faces/channel-nick/settings">settings</a>
to switch between upload date and exif date.<br>
Be aware that pictures without exif date will not be shown if
the exif date is the filter criterion.
</p>

<h3><button class="btn" id="button-faces-hide-frames"><i class="fa fa-eye-slash fa-2x"></i></button> Toogle Frames</h3>
<p>    
    Hide the frames for better visibility of faces.
</p>

<h3>
    <button class="btn faces_zoom" id="button_faces_zoom_in"><i
            class="fa fa-search-plus fa-2x"></i></button>
    <button class="btn faces_zoom" id="button_faces_zoom_out"><i
            class="fa fa-search-minus fa-2x"></i></button> Zoom
</h3>
<p>
    Show one or up to six images in one row. Set the default zoom under
    <a class='link_correction' href="faces/channel-nick/settings">settings</a>.
</p>

<hr/>

<h1>Technical Background</h1>

<h2>Basic Steps</h2>

<h3>1. Face Detection</h3>

<p>
    Find a face and its position in a picture, cut the face
    out. Available detectors:
</p>
<ul>
    <li>retinaface</li>
    <li>mtsnn</li>
    <li>ssd</li>
    <li>opencv</li>
    <li>mediapipe (Google)</li>
</ul>
<p>
    Hand over the faces to the next step...
</p>

<h3>2. Alignment and Normalization</h3>
<p>
    a) The alignment rotates the face until the
    eyes sit on the same horizontal line.
</p>
<p>
    b) The normalization corrects the perspective,
    light, face expression (duck face, smile,...) and produces a kind of
    neutral looking avatar face. The result is handed over to the next step.
</p>
<p>
    Hand over the faces to the next step...
</p>
<h3>3. Creation of Face Representations</h3>
<p>This process creates a face representation for a face, basically a
    multidimensional vector, a so called embedding or representation.
    The embeddings are created once and are stored in the file face.gzip.</p>
<p>Available face recognition models:</p>
<ul>
    <li>Facenet (Google)</li>
    <li>Facenet512 (Google)</li>
    <li>Deepface (Facebook)</li>
    <li>SFace</li>
    <li>ArcFace</li>
    <li>VGG-Face</li>
    <li>OpenFace</li>
</ul>
<p>
    After you name some faces the next process comes in...
</p>

<h3>4. Matching (Verification)</h3>

<p>
    This process matches face representations (vectors) for similarity.
    Available metrics:
</p>
<ul>
    <li>cosine</li>
    <li>euclidean</li>
    <li>euclidean_l2</li>
</ul>

<h2>Further Reading</h2>

<p>
    Please look at the <a href="https://github.com/serengil/deepface">official
        documentation</a> and <a href="https://sefiks.com/talks/">public talks</a>
    of Sefik Ilkin Serengil who is the author of the underlying backend deepface.
</p>

<hr>

<h2>Settings</h2>
<p>
    Open with <a class='link_correction' href="faces/channel-nick/settings">settings</a>.
</p>

<hr>

<h2>Remove</h2>

<p>
    Open with <a class='link_correction' href="faces/channel-nick/remove">remove</a>.
</p>
<p>
    Remove faces and/or names there. You can also remove faces for a certain
    detetor or a model or a combination of detector and models.
</p>

<hr>

<h2>Thresholds (advanced)</h2>
<p>
    Open with <a class='link_correction' href="faces/channel-nick/thresholds">thresholds</a>.
    Fine tune the thresholds for recognition models.
    You can play around with the thresholds in conjunction with
    <a class='link_correction' href="faces/channel-nick/probe">probe</a>.
    The author of deepface Sefik Ilkin Serengil already fine tuned most thresholds
    <a href="https://sefiks.com/2020/05/22/fine-tuning-the-threshold-in-face-recognition/">see</a>.
</p>

<hr>

<h2>Probe (advanced)</h2>
<p>
    The goal is to determine optimised thresholds that find
    "Jane" in all pictures without finding to much "Jane"s ( =
    false positives = persons that are not "Jane").
</p>
<p>
    Open with <a class='link_correction' href="faces/channel-nick/probe">probe</a>.
    You will find detailed step-by-step instructions there. In short this feature will
    start a search using different thresholds for distance metrics.
    The programm will show you the results
    in a table (csv file).
</p>

<hr>

<h1>Remote Detection and Recognition</h1>

<h2>What and Why</h2>
<p>
    You don't want to run the CPU and RAM consuming task of face recognition
    on your server?
</p>
<p>
    There is a python script that provides the same functionality than the
    script running on the server.
</p>
<p>
    How does it work? Do you remember?
    The cloud files on the server are accessible via webDAV.
</p>
<p>
    Once your local 
    machine is connected with the cloud file storage on the server via webDAV the python script
    on your local machine is able to read the pictures on the server
    and write back the results files.
</p>
<p>
    The script looks for the configuration on the server. Visit
    <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    and 
    <a class='link_correction' href="faces/channel-nick/thresholds">thresholds</a>
    to change it.    
</p>
<p>
    The admin page of the addon has a setting to block the execution of
    the python script on the server. If this is activated
    the user is still able to view the faces in the browser and to set names there.
</p>
<h2>Preparations</h2>
<h3>Connect your Computer to the Cloud Files - WebDAV (Approach 1)</h3>
<p>
    Placeholders used...
</p>
<p>  
    <span style="color:red;">[observer.baseurl]</span>: The base url <span style="color:red;" class='baseurl'></span> of your server and part of the webdav url
    <span style="color:red;" class='webdavurl'></span><br>
    <span style="color:red;">[observer.webname]</span>: Login name, same as in browser, <span style="color:red;" class='webname'></span><br> 
    <span style="color:red;">&lt;webdavpassword&gt;</span>: xxx, same as in browser<br>
    <span style="color:red;">&lt;localuser&gt;</span>: The username of the currently logged on local user <br>
</p>
<p>  
    If you are not sure about <span style="color:red;">&lt;localuser&gt;</span> open a terminal and type
</p>
<code>
    whoami
</code>
<br>
<h4>Setup davfs</h4>
<p>
    The steps depend on your operating system.
</p>
<p>
    If you need some hints please have look into the code below.
</p>
<p>
    If you are on Debian you could use the script like this...<br>
    Create a file install_davfs2.sh. Copy the content below into the file.
</p>
<code>
    #!/bin/bash<br>
    #<br>
    function usage {<br>
    &nbsp;&nbsp;&nbsp;&nbsp;echo "usage: $0 url webname user pass local-user"<br>
    &nbsp;&nbsp;&nbsp;&nbsp;echo "    1. url:  [observer.baseurl]/dav<br>
    &nbsp;&nbsp;&nbsp;&nbsp;echo "    2. user: [observer.webname]"<br>
    &nbsp;&nbsp;&nbsp;&nbsp;echo "    3. pass: xxx"<br>
    &nbsp;&nbsp;&nbsp;&nbsp;echo "    4. local-user: whoami" (type as normal user under linux into the console) <br>
    &nbsp;&nbsp;&nbsp;&nbsp;exit<br>
    }<br>
    if [ $# -eq 0 ]; then usage; fi<br>
    <br>
    if [ $# -ne 4 ]; then usage; fi<br>
    <br>
    echo "This are the parameters you gave..."<br>
    url_webdav="$1"<br>
    user_webdav="$2"<br>
    pass_webdav="$3"<br>
    user_dav="$4"<br>
    echo "url=$url_webdav"<br>
    echo "user=$user_webdav"<br>
    echo "pass=$pass_webdav"<br>
    echo "local-user=$user_dav"<br>
    <br>
    read -n1 -p "Continue? [y,n]" doit <br>
    case $doit in  <br>
    &nbsp;&nbsp;&nbsp;&nbsp;y|Y) echo yes ;; <br>
    &nbsp;&nbsp;&nbsp;&nbsp;n|N) echo " stop";exit ;; <br>
    &nbsp;&nbsp;&nbsp;&nbsp;*) echo " stop";exit ;; <br>
    esac<br>
    <br>
    echo "install davfs2 ..."<br>
    DEBIAN_FRONTEND=noninteractive apt-get -q -y install davfs2<br>
    <br>
    echo "making WebDAV available for users other then root"<br>
    # method 1<br>
    chmod u+s /usr/sbin/mount.davfs<br>
    # method 2<br>
    # sudo dpkg-reconfigure davfs2<br>
    <br>
    echo "add $user_dav to group davfs2"<br>
    #usermod -a -G davfs2 $user_dav<br>
    adduser $user_dav davfs2<br>
    <br>
    full_url=$url_webdav/$user_webdav<br>
    echo "check entry for $full_url in /etc/fstab"<br>
    if grep "$full_url" /etc/fstab; then<br>
    &nbsp;&nbsp;&nbsp;&nbsp;echo "found $full_url already in /etc/fstab..."<br>
    else<br>
    &nbsp;&nbsp;&nbsp;&nbsp;echo "adding entry in /etc/fstab..."<br>
    &nbsp;&nbsp;&nbsp;&nbsp;echo "$full_url/ /home/$user_dav/webdav_$user_webdav davfs rw,noauto,user 0 0" >> /etc/fstab<br>
    &nbsp;&nbsp;&nbsp;&nbsp;echo "reload fstab"<br>
    &nbsp;&nbsp;&nbsp;&nbsp;mount -a<br>
    &nbsp;&nbsp;&nbsp;&nbsp;systemctl daemon-reload<br>
    fi <br>
    <br>
    echo "make directory /home/$user_dav/.davfs2 if it does not exist"<br>
    mkdir -p /home/$user_dav/.davfs2<br>
    <br>
    mount_point="/home/$user_dav/webdav_$user_webdav"<br>
    user_credential_file="/home/$user_dav/.davfs2/secrets"<br>
    echo "check entry in user credential file"<br>
    if grep "$mount_point" "$user_credential_file"; then<br>
    &nbsp;&nbsp;&nbsp;&nbsp;echo "found $mount_point already in $user_credential_file"<br>
    else<br>
    &nbsp;&nbsp;&nbsp;&nbsp;echo "add entry for $mount_point in $user_credential_file"<br>
    &nbsp;&nbsp;&nbsp;&nbsp;echo "$mount_point $user_webdav $pass_webdav" >> $user_credential_file<br>
    fi <br>
    mkdir -p "$mount_point"<br>
    <br>
    echo "setting file permission on /home/$user_dav/.davfs2"<br>
    chown -R $user_dav /home/$user_dav/.davfs2<br>
    chmod 600 /home/$user_dav/.davfs2/secrets<br>
    <br>
    user_config_file="/home/$user_dav/.davfs2/davfs2.conf"<br>
    echo "check existence of file $user_config_file"<br>
    if [ ! -f "$user_config_file" ]; then<br>
    &nbsp;&nbsp;&nbsp;&nbsp;echo "copy davfs2.conf to $user_config_file"<br>
    &nbsp;&nbsp;&nbsp;&nbsp;cp /etc/davfs2/davfs2.conf /home/$user_dav/.davfs2/<br>
    fi<br>
    <br>
    # optional in /home/$user_dav/.davfs2/.davfs2/davfs2.conf<br>
    #   if_match_bug 1<br>
    #   use_locks 0<br>
    #   cache_size 1 <br>
    #   table_size 4096<br>
    #   delay_upload 1<br>
    #   gui_optimize 1<br>
    <br>
    <br>
    echo "Finished."<br>
    echo " "<br>
    echo "Usage:"<br>
    echo "&nbsp;&nbsp;&nbsp;&nbsp;to mount type   'mount $mount_point'"<br>
    echo "&nbsp;&nbsp;&nbsp;&nbsp;to unmount type 'umount $mount_point'"<br>

    ########################
    # doc
    ########################
    # http://skripta.de/Davfs2.html
    # http://ajclarkson.co.uk/blog/auto-mount-webdav-raspberry-pi/

</code>
<p>
    Run the file under root
</p>
<code>
    su -<br>
    install_davfs2.sh <span style="color:red;" class='baseurl'></span>/dav <span style="color:red;" class='webname'></span> "<span style="color:red;">&lt;webdavpassword&gt;</span>" <span style="color:red;">&lt;localuser&gt;</span>
</code>
<p>
    To mount the cloud files as user <span style="color:red;">&lt;localuser&gt;</span>
</p>
<code>
    mount /home/<span style="color:red;">&lt;localuser&gt;</span>/webdav_<span style="color:red;" class='webname'></span>
</code>
<p>
    To unmount
</p>
<code>
    umount /home/<span style="color:red;">&lt;localuser&gt;</span>/webdav_<span style="color:red;" class='webname'></span>
</code>
<br>
<h3>Connect your Computer to the Cloud Files - File Manager (Approach 2)</h3>
<p>
    Link your photos  as a network drive with you file mangager
</p>
<p>
    Linux
</p>
<code>
    <span style="color:red;" class='webdavurllinux'></span><br>
</code>
<p>
    Windows
</p>
<code>
    <span style="color:red;" class='webdavurl'></span><br>
</code>
<p>
    To see the webdav share in your Home directory (Linux): 
</p>
<code>
    ln -s /run/user/1000/gvfs/dav:host=<span style="color:red;" class='domainname'></span>,ssl=true,prefix=%2Fdav/<span style="color:red;" class='webname'></span> /home/<span style="color:red;">&lt;localuser&gt;</span>/webdav_<span style="color:red;" class='webname'></span>
</code>

<br>
<h3>Install Python Package Manager and Python Modules</h3>
<p>
    The following was tested under Debian 11.
</p>
<p>
    Python Package Manager
</p>
<code>
    su -<br>
    apt-get update<br>
    apt-get -y install python3-pip<br>
    pip --version
</code>
<p>
    Python Modules
</p>
<code>
    pip install deepface mediapipe fastparquet pyarrow
</code>
<br>
<h3>Update Python Modules</h3>
<p>
    If you want to update the python modules the
</p>
<code>
    pip install --upgrade deepface mediapipe fastparquet pyarrow
</code>
<p>
    Alternatively upgrade to the latest possible versions...
</p>
<code>
    pip install --upgrade --upgrade-strategy eager deepface mediapipe fastparquet pyarrow
</code>
<br>
<h3>Download the Script</h3>
<p>
    Do this only once
</p>
<code>
    sudo apt-get install git<br>
    cd ~<br>
    git clone https://codeberg.org/streams/streams-addons.git
</code>
<p>
    Every time you want to run the script...
</p>
<p>
    Preparation: Connect via webDAV (if you followed approach 1)...
</p>
<code>
    mount /home/<span style="color:red;">&lt;localuser&gt;</span>/webdav_<span style="color:red;" class='webname'></span>
</code>
<br>
<h2>Run the script:</h2>
<code>
    cd ~<br>
    cd streams-addons/faces/py/<br>
    python3 run.py -d /home/<span style="color:red;">&lt;localuser&gt;</span>/webdav_<span style="color:red;" class='webname'></span>
</code>
<p>
    Print all parameters:
</p>
<code>
    python3 run.py --help
</code>

<hr/>

<h1>Privacy</h1>

<p>Keep in mind: no upload of an image, no face detection.</p>
<p>
    If you run your own server:
</p>
<ul>
    <li>Your faces (names and face representations) will not leave your server.</li>
</ul>
<p>
    If you use a public server (a European perspective):
</p>
<ul>
    <li>User consent: Activate/deactivate the face recognition yourself, 
        <a href="https://gdpr-info.eu/art-7-gdpr/">Art. 7 GDPR</a>. There is no server wide face recognition.
    </li>
    <li>Data protection by design and by default: Allow or deny users/groups to view and edit your faces and names,
        <a href="https://gdpr-info.eu/art-25-gdpr/">Art. 25 GDPR</a>.
    </li>
    <li>Right to data portability, <a href="https://gdpr-info.eu/art-20-gdpr/">Art. 20 GDPR</a>.:
        <ul>
            <li>Export your faces and names,</li>
            <li>Import your faces and names from a different provider,</li>
            <li>Faces and names are synchronized automatically to your channel clones and are kept in sync.</li>
        </ul>
    </li>
    <li>Right to rectification: Correct you faces and names at any time, 
        <a href="https://gdpr-info.eu/art-16-gdpr/">Art. 16 GDPR</a>.
    </li>
    <li>Right to erasure (‘right to be forgotten’): Delete your faces and names at any time, 
        <a href="https://gdpr-info.eu/art-17-gdpr/">Art. 17 GDPR</a>,
    <li>Right to object: <a href="https://gdpr-info.eu/art-21-gdpr/">Art. 21 GDPR</a>
    </li>
</ul>
<p>
    If you were tagged by others: Visit their page of the addon and append
    <strong>/me</strong> at the end of the URL. 
</p>
<ul>
    <li>
        Example: You tagged some of your contacts. Send this link
        <a class='link_correction' href="faces/channel-nick/me"><span class='addonurl'></span>/me</a>
        to your contact. Your contact can now (view and) remove his faces.
        (He must be logged on.)
    </li>
</ul>


<hr>

<h1>Why this Addon?</h1>

<h2>
    Reclaim Artificial Intelligence (AI) from private Companies
</h2>
<p>
    To recognizes faces you will usually use the service of a private company.
    Keep in mind, companies have to make money. They will keep, sell and re-use your data in their own
    interest without asking you.
</p>
<p>
    Keep the data where it belongs to - to YOU.
</p>
<h2>
    Make your own Experiments
</h2>
<p>
    ...and choose what works best for you.
</p>
<p>
    This addon bundles some recent face recognition methods
    from universities as well as big companies like Google and Facebook.
</p>
<p>
    This addon makes it easy for you to play around with some parameters without
    the need of programming skills:
</p>
<ul>
    <li>
        Choose detectors (task = this is a FACE), see
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    </li>
    <li>
        Choose models (task = this face is JANE), see
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    </li>
    <li>
        Combine detectors and models. Be aware that 5 detectors combined with
        7 models will produce 35 faces (instead of one). All have to be created, stored and matched. See
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    </li>
    <li>
        Set a minimum size for a face to be detected, see
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    </li>
    <li>
        Set the minimum size of know faces used to search in other images (to train the model), see
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    </li>
    <li>
        Set the minimum size of unknown faces to be matched with known faces, see
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    </li>
    <li>
        Choose a distance metric to match faces, see
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    </li>
    <li>
        Choose a threshold of confidence for the recognition (this face is JANE), see
        <a class='link_correction' href="faces/channel-nick/thresholds">thresholds</a>
        <br>The optimal value of a threshold depends mainly on the combination of a model and a distance metric
        but also on the detector used by a model.
        The author of deepface Sefik Ilkin Serengil already fine tuned the thresholds, more
        <a href="https://sefiks.com/2020/05/22/fine-tuning-the-threshold-in-face-recognition/">background</a>.
    </li>
</ul>
<p>
    Parameters you can not set:
</p>
<ul>
    <li>Threshold of confidence for the detection (this is a FACE)</li>
    <li>Method to align and normalize faces to increase the accuracy</li>
</ul>
<h2>
    How well does modern Face Recognition work?
</h2>
<p>
    In theory modern recognition models have very high recognition rates of over 99%, 
    <a href="https://github.com/serengil/deepface">see</a>.    
    How well does face recognition work with real live photos,
    partly visible faces, hair in the face, helmets, glasses, seen from different
    angles, image noise,...?
</p>
<p>
    Just proove it using this software!
</p>
<p>
    AI ("artifical intelligence" we should better call it machine learning)
    is conquering more and more aspects of our lifes.
    Most of us will use face recognition for fun.
    Some just search their foto album. Others search for relatives using payed websites.
    Sometimes the consequences of this technology are quite serious.
    People can land on terrorist lists or get blackmailed.
</p>
<p>
    How many false positives are produced by differnet detectors and recognition models?
</p>
<p>
    Of course the big players like Google, Apple, Amazon,... have a bunch of other data
    to make a better prediction than this software can do for you.
    They will use timestamps in combination with the location data in images, 
    the social circle, nearby bluetooth devices and and other data to tell you who is most likly on a picture.
    This is a different story. Face recognition itself can do no more magic than for
    you.
</p>

<script src="/addon/faces/view/js/help.js"></script>
