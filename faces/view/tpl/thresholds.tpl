
<h1 id="face_thresholds">Thresholds</h1>
<button class="btn" id="face-edit-set-name" onclick="setDefaults()"><i class="fa fa-compass fa-2x"></i>
</button>

<form id="face_form_thresholds" method="post" action="http://localhost/admin/addons/faces/" class="">
    <p id="faces_thresholds_explain"></p>

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

<script src="/addon/faces/view/js/thresholds.js"></script>