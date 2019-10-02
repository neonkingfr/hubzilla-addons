<div class="workflow-header">
	<div class="title col-12">{{$title}}</div>
	<div class="headerxtras col-12">{{$headerextras}}</div>
</div>
<div class="workflow-item-list">
{{foreach $items as $item}}
<div class="row">
   <div class="workflow-item-{{cycle values="odd,even"}} col-12">
	<div class="row" style="background-color:rgba(0,0,0,0.2);font-size:2em;font-weight:heavy;">
		<div class="col-xs-12 col-sm-9"><a href="{{$item.url}}" target=_{{$item.target}}>{{$item.title}}</a></div>
		<div class="col-xs-12 col-sm-3" style="font-size:.5em;font-weight:normal;">{{$item.channelname}}</div>
	</div>
	<div class="row" style="background-color:rgba(0,0,0,0.3);">
		<div class="col-sm-12">{{$item.body}}</div>
	</div>
	<div class="row" style="background-color:rgba(0,0,0,0.4);">
		<div class='col-sm-12 wfmeta-container'>{{$item.listextras}}</div>
	</div>
	<div class="row" style="background-color:#fff; width:100%;font-size:.2em;">
		<div class='col-12'>&nbsp;</div>
	</div>
   </div>
</div>
{{/foreach}}
</div>
