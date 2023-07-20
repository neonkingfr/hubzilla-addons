<div id="face-scroll-top-message">Loading...</div>
<div id="face-panel-remove-button">
        <button class="btn" id="face-button-remove-me" onclick="removeMe()">Remove Me</button>
        from <span id="face-span-image-count">x</span> more image(s) not
        shown below. You don't see all images because of missing permissions.
</div>
<div id="face-panel-pictures" class="clearfix">
    <!-- 
    -->
    <!-- filled dynamically by javascript -->
    <!-- all the pictures go here -->
</div>
<div id="face-scoll-end"></div>

<div style="display: none;">
    <p>    
        Addon Faces v{{$version}} ).
    </p>
    <p>
        Feedback of face recognition...<br>
        status: {{$status}}<br>
        message: {{$message}}
    </p>
    <p id="faces_can_write">{{$can_write}}</p>
    <p id="faces_is_owner">{{$is_owner}}</p>
    <p id="faces_log_level">{{$log_level}}</p>
    <p id="faces_can_write">{{$can_write}}</p>
</div>

<!-- this div will be copied by javascript and then removed after loading the page -->
<div id="template-face-frame-edit-controls" style="display: none;">
    <div class="d-flex justify-content-center">
        <div>
            <input type="text" id="input-face-name" name="face-search-faces" list="face-name-list-set"
                   class="form-control input-face-name-list" onchange="setName()">
            <datalist id="face-name-list-set" class="face-name-list">
                <!-- filled dynamically by javascript -->
            </datalist>
        </div>
    </div>
    <div>
        <button class="btn" id="face-edit-set-name" onclick="setName()"><i class="fa fa-thumbs-up fa-2x"></i>
        </button>
        <button class="btn" id="face-edit-set-unknown" onclick="setNameUnkown()"><i
                class="fa fa-question fa-2x"></i> </button>
        <button class="btn" id="face-edit-set-ignore" onclick="setNameIgnore()"><i
                class="fa fa-eye-slash fa-2x"></i></button>
    </div>
</div>
<!-- controls that are permanently shown at the bottom of the page -->
<div id="face-footer-buttons">
    <div class="d-flex justify-content-center">
        <div>
            <button class="btn" id="button-faces-filter"><i class="fa fa-filter fa-2x"></i></button>
        </div>
        <div>
            <button class="btn" id="button-faces-hide-frames"><i class="fa fa-eye-slash fa-2x"></i></button>
        </div>
        <div>
            <button class="btn" id="button_share_box">
                <span id="button_share_box_counter_upload"></span>
                <i class="fa fa-refresh fa-2x"></i>
                <span id="button_share_box_counter_download"></span>
            </button>
        </div>
        <div id="faces_server_status"></div>
        <div>
            <button class="btn faces_zoom" id="button_faces_zoom_in"><i
                    class="fa fa-search-plus fa-2x"></i></button>
        </div>
        <div>
            <button class="btn faces_zoom" id="button_faces_zoom_out"><i
                    class="fa fa-search-minus fa-2x"></i></button>
        </div>
    </div>
</div>
<!-- controls for the search filter fixed at the bottom of the page -->
<div id="face-footer">
    <div id="panel_faces_filter">

        <div>
            <div class="form-group d-flex justify-content-center" id="face-active-filter-names">
                <!-- filled dynamically by javascript -->
            </div>
            <div class="foorm-group d-flex justify-content-center" style="display: none;">
                <label class="face-name-and-buttons">AND <input type="checkbox" id="face-search-and"
                                                                class="face-name-and-buttons faces-search-inputs"></label>
            </div>
            <div class="form-group d-flex justify-content-center">
                <input type="text" id="input-search-name" name="input-face-name-list"
                       list="face-name-list-search" class="form-coontrol input-face-name-list faces-search-inputs"
                       autocomplete="off" onchange="addSearchName(this)">
                <datalist id="face-name-list-search" class="face-name-list">
                    <!-- filled dynamically by javascript -->
                </datalist>
            </div>
            <div class="form-group d-flex justify-content-center">
                <input type="date" id="face-date-from" class="face-date faces-search-inputs"
                       name="face-date-from" class="form-control">
                <label for="face-date-from"> <i class="fa fa-arrow-left fa-2x"></i> </label>
                <label for="face-date-to"> <i class="fa fa-arrow-right fa-2x"></i> </label>
                <input type="date" id="face-date-to" class="face-date faces-search-inputs" name="face-date-to"
                       class="form-control">
            </div>
        </div>
    </div>
</div>
<script src="/addon/faces/view/js/faces.js"></script>