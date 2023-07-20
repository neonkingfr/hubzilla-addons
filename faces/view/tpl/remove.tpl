<form id="face_form_remove" method="post" action="http://localhost/admin/addons/faces/" class="">

    <h1>Remove Faces from the Results</h1>
    <p>Select the detectors and models you want to remove and press "submit".</p>

    <p>Reasons why to remove results</p>
    <ul>
        <li>Speed up the face recognition and reduce server load
        <li>Remove detected faces in images that are no faces. Some detectors
            tend to deliver more false positives than others, e.g. OpenCV.
    </ul>


    <div id="face_detectors">
        <h2>Face Detectors</h2>
    </div>
    <div id="face_models">
        <h2>Face Recognition Models</h2>
    </div>
    <h1>Clear all Names</h1>
    <p id="face_names">Clear all names both set by the user and recognized.</p>
    <div class="submit">
        <input type="submit" name="page_faces" value="Submit" class="float-right">
    </div>

    <div id="placeholdername_container" class="clearfix form-group checkbox">
        <label for="id_placeholdername">placeholdername</label>
        <div class="float-right">
            <input type="checkbox" name="placeholdername" id="id_placeholdername" value="1" checked="checked">
            <label class="switchlabel" for="id_placeholdername">
                <span class="onoffswitch-inner" data-on="" data-off=""></span>
                <span class="onoffswitch-switch"></span>
            </label>
        </div>
    </div>

</form>

<div style="display: none;">
    <p>    
        Addon Faces v{{$version}} ).
    </p>
    <p id="faces_log_level">{{$loglevel}}</p>
</div>

<script src="/addon/faces/view/js/remove.js"></script>