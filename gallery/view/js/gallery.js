import PhotoSwipeLightbox from '/addon/gallery/lib/photoswipe5/dist/photoswipe-lightbox.esm.min.js';

$(document).ready(function() {

	let selector = '.wall-item-body img, .wall-photo-item img';
	let imgMinSize = 300;

	$(document).on('click', selector, function(e) {
		if(e.target.naturalWidth < imgMinSize && e.target.naturalHeight < imgMinSize) {
			return;
		}

		e.preventDefault();
		e.stopPropagation();

		let items = [];
		let startImage;
		let id;
		let caption;
		let link;
		let img;
		let obj;

		if($(e.target).closest('.wall-photo-item').length)
			id = $(e.target).closest('.wall-photo-item').attr('id');

		if($(e.target).closest('.reshared-content').length)
			id = $(e.target).closest('.reshared-content').attr('id');

		if(! id)
			id = $(e.target).closest('.wall-item-body').attr('id');

		img = $('#' + id).find('img');

		img.each( function (index, item) {
			if(item.naturalWidth < imgMinSize && item.naturalHeight < imgMinSize)
				return;

			if(item.src == e.target.src)
				startImage = index;

			if(item.parentElement.tagName == 'A')
				link = decodeURIComponent(item.parentElement.href);

			caption = item.title;

			if (link) {
				caption = caption ? caption + '<br>' : caption;
				caption = caption + '<b>This image is linked:</b> click <a href="' + link + '" target="_blank"><b>here</b></a> to follow the link!';
			}

			obj = {
				src: item.src,
				msrc: item.src,
				width: item.naturalWidth,
				height: item.naturalHeight,
				caption: caption
			};

			items.push(obj);

		});

		if(! items.length)
			return;

		var options = {
			dataSource: items,
			bgOpacity: 1,
			bgClickAction: 'toggle-controls',
			pswpModule: () => import('/addon/gallery/lib/photoswipe5/dist/photoswipe.esm.js'),
		};

		const lightbox = new PhotoSwipeLightbox(options);

		lightbox.on('uiRegister', function() {
		  lightbox.pswp.ui.registerElement({
			name: 'custom-caption',
			order: 9,
			isButton: false,
			appendTo: 'root',
			html: '',
			onInit: (el, pswp) => {
				lightbox.pswp.on('change', () => {
				if (lightbox.pswp.currSlide.data.caption) {
					el.innerHTML = lightbox.pswp.currSlide.data.caption;
				}
				else {
					el.remove();
				}
			  });
			}
		  });
		});

		lightbox.init();
		lightbox.loadAndOpen(startImage);
	});

	$(document).on('mouseenter', selector, function(e) {
		if(e.target.naturalWidth < imgMinSize && e.target.naturalHeight < imgMinSize) {
			return;
		}

		$(this).css('cursor', 'zoom-in');
	});

});


