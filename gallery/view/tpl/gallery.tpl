{{if !$aj}}
<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>{{$title}}</h2>
	</div>
	<div class="section-content-wrapper-np">
		<div id="photo-albums" style="display: none">
			{{foreach $albums as $album}}
				<div class="init-gallery cursor-pointer" data-aid="{{$album.folder}}" data-album="{{$album.album}}">
					<img src="photo/{{$album.resource_id}}-3" width="{{$album.width}}" height="{{$album.height}}" alt="{{$album.album}}" />
				</div>
			{{/foreach}}
		</div>
	</div>
</div>
{{/if}}

<!-- Root element of PhotoSwipe. Must have class pswp. -->
<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true">

	<!-- Background of PhotoSwipe. 
		 It's a separate element as animating opacity is faster than rgba(). -->
    <div class="pswp__bg"></div>

	<!-- Slides wrapper with overflow:hidden. -->
    <div class="pswp__scroll-wrap">

		<!-- Container that holds slides. 
			PhotoSwipe keeps only 3 of them in the DOM to save memory.
			Don't modify these 3 pswp__item elements, data is added later on. -->
		<div class="pswp__container">
			<div class="pswp__item"></div>
			<div class="pswp__item"></div>
			<div class="pswp__item"></div>
		</div>

		<!-- Default (PhotoSwipeUI_Default) interface on top of sliding area. Can be changed. -->
		<div class="pswp__ui pswp__ui--hidden">

			<div class="pswp__top-bar">

				<!--  Controls are self-explanatory. Order can be changed. -->
				
				<div class="pswp__counter"></div>

				<button class="pswp__button pswp__button--close" title="Close (Esc)"></button>

				<button class="pswp__button pswp__button--share" title="Share"></button>

				<button class="pswp__button pswp__button--fs" title="Toggle fullscreen"></button>

				<button class="pswp__button pswp__button--zoom" title="Zoom in/out"></button>

				<!-- Preloader demo http://codepen.io/dimsemenov/pen/yyBWoR -->
				<!-- element will get class pswp__preloader--active when preloader is running -->
				<div class="pswp__preloader">
					<div class="pswp__preloader__icn">
					  <div class="pswp__preloader__cut">
					    <div class="pswp__preloader__donut"></div>
					  </div>
					</div>
				</div>
			</div>

	        <div class="pswp__share-modal pswp__share-modal--hidden pswp__single-tap">
				<div class="pswp__share-tooltip"></div> 
	        </div>

			<button class="pswp__button pswp__button--arrow--left" title="Previous (arrow left)">
			</button>
			
			<button class="pswp__button pswp__button--arrow--right" title="Next (arrow right)">
			</button>

			<div class="pswp__caption">
				<div class="pswp__caption__center"></div>
			</div>

	    </div>

	</div>

</div>

<script>
	{{if ! $aj}}
	justifyPhotos('photo-albums');
	{{/if}}

	var gallery = {};
	var aid = '';
	var album = '';
	var share_str = '';

	// items array
	{{if ! $aj}}
	var items = [];
	{{/if}}

	{{if $json}}
	var items = {{$json}};
	{{/if}}

	// define options
	var options = {
		index: 0, // start at first slide
		preload: [1, 3],
		shareButtons: [
			{{if ! $aj}}
			{ id: 'conv_link', label: 'View conversation', url: 'photos/{{$nick}}/image/\{\{raw_image_url\}\}' },
			{{/if}}
			{ id: 'download', label: 'Download fullsize image', url: 'photo/\{\{raw_image_url\}\}', download: true }
			],
		getImageURLForShare: function( shareButtonData ) {
			return gallery.currItem.resource_id;
		}
	};

	var pswpElement = document.querySelectorAll('.pswp')[0];

	if(items.length) {
		// Initializes and opens PhotoSwipe
		gallery = new PhotoSwipe(pswpElement, PhotoSwipeUI_Default, items, options);
		gallery.init();
	}
	{{if ! $aj}}
	$(document).on('click', '.init-gallery', function() {
		album_id = $(this).data('aid');
		album = $(this).data('album');
		share_str = '';
		$.post(
			'gallery/{{$nick}}',
			{
				'album_id' : album_id,
				'album' : album,
				'unsafe' : {{$unsafe}}
			},
			function(items) {
				var i;
				for (i = 0; i < (items.length > 1 ? 1 : items.length); i++) { //keep preview image count at 1 until we have a solution to nicely present multiple photos 
					share_str += '[zrl=' + encodeURIComponent(baseurl + '/gallery/{{$nick}}/' + album + '?f=&gid=1&pid=' + (i+1)) + '][zmg]' + encodeURIComponent(items[i].src) + '[/zmg][/zrl]';
				}
				options.shareButtons.splice(2, 1, { id: 'share_link', label: 'Share this album', url: 'rpost?f=&title=' + encodeURIComponent('Album: ' + album) + '&body=' + share_str });
				// Initializes and opens PhotoSwipe
				gallery = new PhotoSwipe(pswpElement, PhotoSwipeUI_Default, items, options);
				gallery.init();
			},
			'json'
		);
	});
	{{/if}}
</script>
