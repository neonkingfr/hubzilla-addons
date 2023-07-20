<h1>Selfchecks</h1>
{{$pythoncheckmsg}}
{{$deepfacecheckmsg}}
{{$mysqlconnectorcheckmsg}}
{{$ramcheckmsg}}
<h1>Configuration</h1>
<h2>Block Detection and Recognition on this Server</h2>
{{include file="field_checkbox.tpl" field=$block}}
<h2>Face Detectors</h2>
{{include file="field_checkbox.tpl" field=$retinaface}}
{{include file="field_checkbox.tpl" field=$mtcnn}}
{{include file="field_checkbox.tpl" field=$ssd}}
{{include file="field_checkbox.tpl" field=$mediapipe}}
{{include file="field_checkbox.tpl" field=$opencv}}
<h2>Face  Recognition Models</h2>
{{include file="field_checkbox.tpl" field=$Facenet512}}
{{include file="field_checkbox.tpl" field=$ArcFace}}
{{include file="field_checkbox.tpl" field=$VGGFace}}
{{include file="field_checkbox.tpl" field=$Facenet}}
{{include file="field_checkbox.tpl" field=$OpenFace}}
{{include file="field_checkbox.tpl" field=$DeepFace}}
{{include file="field_checkbox.tpl" field=$SFace}}
<h2>Distance Metrics</h2>
{{include file="field_checkbox.tpl" field=$euclidean}}
{{include file="field_checkbox.tpl" field=$cosine}}
{{include file="field_checkbox.tpl" field=$euclidean_l2}}
<h2>Facial Attributes and Demography</h2>
{{include file="field_checkbox.tpl" field=$Emotion}}
{{include file="field_checkbox.tpl" field=$Age}}
{{include file="field_checkbox.tpl" field=$Gender}}
{{include file="field_checkbox.tpl" field=$Race}}
<h2>Performance</h2>
{{include file="field_checkbox.tpl" field=$experimental_allowed}}
{{include file="field_checkbox.tpl" field=$immediatly}}
{{include file="field_input.tpl" field=$max_ram}}
<h2>Browser Appearance</h2>
{{include file="field_input.tpl" field=$zoom}}
<p>Pressing the button <b>"Submit and Test"</b> will start to load all modules configured above to
test their availability and if there is sufficient RAM. This <b>will take a while</b>.</p>
<div class="submit"><input type="submit" name="page_faces" value="{{$submit}}"></div>
