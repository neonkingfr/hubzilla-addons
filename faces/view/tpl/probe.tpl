
<h1>Probe Thresholds</h1>

<form id="face_form_probe" method="post" action="http://localhost/admin/addons/faces/" class="">
    <h2>Why this?</h2>
    <p>
        ...to help you to 
    </p>
    <ul>
        <li>fine tune the face recognition in case you experience          
            <ul>
                <li>to many false positives, e.g. faces that are not Bob or Jane</li>
                <li>to less matches, e.g. the face recognition finds to less faces of Jane and Bob</li>
            </ul>
            by <a class='link_correction' href="faces/channel-nick/thresholds">overwriting</a> the default thresholds</li>
        <li>compare the accuracy of combinations of detector, model and distance metric
            and help you to choose one that works best for you</li>
    </ul>
    <h2>What will happen?</h2>
    <p>
        If you press "submit" this will start a face recognition that
        iterates through all combinations of
    </p>
    <ul>
        <li>detectors</li>
        <li>models</li>
        <li>distance metrics</li>
    </ul>
    <p>
        you configured under <a class='link_correction' href="faces/channel-nick/settings">settings</a>  where each distance metric is
        iterated in 10 steps from 50% to 150% of its default value 
        for each model-detector combination and writes the results into a file
        <a class='link_correction' href="cloud/channel-nick/faces/probe.csv">probe.csv</a>.
    </p>
    <p>
        Example: The default Cosine is 0.4 for model Facenet. The recognition will iterate
        in ten steps from 0.2 to 0.6.
    </p>
    <h2>Step-for-Step Instructions</h2>
    <ul>
        <li>
            Train the face recogntion with the faces of Bob and Jane.
            <ul>
                <li>
                    Upload some pictures of Bob and Jane. 
                    (It does not matter if other persons than Bob and Jane are shown in the pictures.)
                </li>
                <li>
                    Use the <a class='link_correction' href="faces/channel-nick">addon</a> 
                    to detect and name faces of Jane and Bob you uploaded before.
                    <br>Hint: The browser sorts pictures for upload time. Pictures that you
                    just uploaded will land on top of older pictures.
                    <br>Hint: You can sort the pictures for the time they where shot.
                    Please use the setting "exif" in
                    the <a class='link_correction' href="faces/channel-nick/settings">settings</a>
                    page.
                </li>
            </ul>
        </li>
        <li>
            Upload some more pictures of Bob and Jane. DO NOT NAME THEM.
            <ul>
                <li>
                    Upload some pictures of
                    <ul>
                        <li>
                            Jane into <a class='link_correction' href="cloud/channel-nick/faces/probe/Jane">Jane</a>
                        </li>
                        <li>
                            Bob into <a class='link_correction' href="cloud/channel-nick/faces/probe/Bob">Bob</a>
                        </li>
                        <li>...more persons as you like</li>
                    </ul>
                <li>
                    Attention: The spelling of the name and the directory must by identical.
                </li>
                <li>
                    Attention: Each picture must show one person only, e.g. Jane, Bob,...
                </li>
                <li>
                    Attention: Do not set names for them. Leave this to the face recognition.
                    That is the purpose of the whole exercise: The face recognition
                    should find Jane and Bob in every single picture.
                </li>
            </ul>
        </li>
        <li>
            Upload some pictures of persons the face recognition was not trained for.
            <ul>
                <li>
                    Copy some pictures WITHOUT Jane and Bob into the directory
                    <a class='link_correction' href="cloud/channel-nick/faces/probe/unknown">unknown</a>.
                    That is the purpose of the whole exercise: The face recognition 
                    should not find Jane and Bob in this directory.
                </li>
            </ul>
        </li>
        <li>
            Select the detectors, models and distance metrics you want to probe 
            in <a class='link_correction' href="faces/channel-nick/settings">settings</a>
        </li>
        <li>
            Come back <a class='link_correction' href="faces/channel-nick/probe">here</a> 
            and press the button "submit" below. Alternativly mount the cloud files
            via webdav and start the face recognition on your computer...
            <code>
                python3 faces/py/run.py -d ~/dir-to-webdav/ <strong>--probe</strong> --loglevel 2 --logfile ~/faces.log
            </code>
        </li>
        <li>
            Look for the results in 
            <a class='link_correction' href="cloud/channel-nick/faces/probe.csv">probe.csv</a>
            <br>There is no feedback when the process is finished.
            <br>You might watch the logfile or delete
            <a class='link_correction' href="cloud/channel-nick/faces/probe.csv">probe.csv</a>
            in <a class='link_correction' href="cloud/channel-nick/faces">faces</a>
            before.
        </li>
    </ul>

    <h2>Interpretation</h2>
    <p>
        The results in <a class='link_correction' href="cloud/channel-nick/faces/probe.csv">probe.csv</a>
        should be self-explanatory.
    </p>
    <p>
        What values are the best to choose as thresholds for a distance metric?
    </p>
    <p>
        Ideally all faces
    </p>
    <ul>
        <li>
            of Jane in directory <a class='link_correction' href="cloud/channel-nick/faces/probe/Jane">Jane</a>
        </li>
        <li>
            of Bob in directory <a class='link_correction' href="cloud/channel-nick/faces/probe/Bob">Bob</a>
        </li>
    </ul>
    <p>
        should be found.
    </p>
    <p>
        Example: The column "Jane" should have the value "6"
        if you uploaded six pictures of Jane into the directory
        <a class='link_correction' href="cloud/channel-nick/faces/probe/Jane">Jane</a>.
    </p>
    <p>
        Ideally no faces should be found in the
        directory <a class='link_correction' href="cloud/channel-nick/faces/probe/unknown">unknown</a>
    </p>
    <p>
        Example: The column "Jane-in-unkown" should have the value "0".
    </p>
    <p>
        To stick to the example, search for columns
    </p>
    <ul>
        <li>
            "Jane" = 6
        </li>
        <li>
            "Jane-in-unkown" = 0
        </li>
    </ul>
    <p>
        Compare the "distance" in page 
        <a class='link_correction' href="faces/channel-nick/thresholds">thresholds</a>
        and correct the values there if necessary.
    </p>
    <p>
        Be aware that a model can use different detectors to find a face.
        The detectors will not cut of a face at the same position with the
        same size in a picture. This results in different embeddings produced by the same
        model for the same face. The thresholds for a detector-model combination will differ 
        accordingly for the same face.
    </p>
    <p>
        To discuss: It might be usefull to
        distinguish the setting for thresholds and use detector-model combination and
        not just models. In praxis it is probably a usefull approach to use just one (the best)
        combination of detector - model - distance metric. This will speed up
        all processes, saves CPU and RAM.
    </p>
    <p>
        In first tests the best results where achieved with
    </p>
    <ul>
        <li>
            detector = retinaface
        </li>
        <li>
            model = Facenet512
        </li>
        <li>
            distance metric = euclidean_l2 = 1.04 (what is the default of 
            the underlying library deepface at the time of writing)
        </li>
    </ul>
    <p>
        Few different combinations come close, most are miles away.
    </p>
    <p>
        It might be helpful to look into the file        
        <a class='link_correction' href="cloud/channel-nick/faces/face_statistics.csv">face_statistics.csv</a>
        in <a class='link_correction' href="cloud/channel-nick/faces">faces</a>.
        This file shows all the details about faces, what detectors found what face,
        what model recognized a face,...
        <br>To fill this file activate the setting "statistics" in the
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
        page and start the face recognition again by reloading the
        <a class='link_correction' href="faces/channel-nick">addon</a>.
    </p>


    <div class="submit">
        <input type="submit" name="page_faces" value="Submit" class="float-right">
    </div>

</form>

<div style="display: none;">
    <p>    
        Addon Faces v{{$version}} ).
    </p>
    <p id="faces_log_level">{{$loglevel}}</p>
</div>

<script src="/addon/faces/view/js/probe.js"></script>