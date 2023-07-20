<form id="face_form_sharing" method="post" action="" class="">


    <h1>Share Faces</h1>
    
    <div id="main-slider" class="slider" >
	<input id="main-range" type="text" name="closeness" value="50" />
    </div>
    
    <div>
        Please move the slider to define the contacts you want to to share faces with.
    </div>
    <div id="faces-contact-list-share" class="form-group">See friend zoom of individual contacts<br/></div>
    <br/>
    <div>Technical note: Shared faces will only have an effect if both parties
        use the the same combination of detector and model.
    </div>

    <hr/>

    <h2>Faces you share</h2>
    <div id="faces-you-share"></div>

    <hr/> 

    <h2>Faces shared with you</h2>
    <div id="faces-shared-with-you"></div>

</form>

<div style="display: none;">
    <p>    
        Addon Faces v{{$version}} ).
    </p>
    <p id="faces_log_level">{{$loglevel}}</p>
</div>

<script src="/addon/faces/view/js/sharing.js"></script>