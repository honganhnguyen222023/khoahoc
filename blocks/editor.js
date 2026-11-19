/**
 * KhoaHoc blocks – editor UI.
 * Plain JavaScript (no build step). Every block is rendered by PHP on the site,
 * so save() only stores inner blocks. Edit views reuse the front-end CSS classes
 * so what you see in the editor matches the website.
 */
(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var be = wp.blockEditor;
	var RichText = be.RichText;
	var InnerBlocks = be.InnerBlocks;
	var InspectorControls = be.InspectorControls;
	var MediaUpload = be.MediaUpload;
	var MediaUploadCheck = be.MediaUploadCheck;
	var useBlockProps = be.useBlockProps;
	var c = wp.components;
	var SSR = wp.serverSideRender;
	var DATA = window.KH_EDITOR || {};
	var ICONS = DATA.icons || {};
	var FORMATS = ['core/bold', 'core/italic', 'core/link'];
	var CAT = 'khoahoc';

	/* ---------- helpers ---------- */

	function Icon(name, size) {
		size = size || 20;
		return el('svg', {
			className: 'kh-svg',
			width: size,
			height: size,
			viewBox: '0 0 24 24',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: 2,
			strokeLinecap: 'round',
			strokeLinejoin: 'round',
			'aria-hidden': true,
			dangerouslySetInnerHTML: { __html: ICONS[name] || '' }
		});
	}

	function str(def) {
		return { type: 'string', default: def === undefined ? '' : def };
	}

	function rt(props, key, tag, placeholder, className) {
		return el(RichText, {
			tagName: tag,
			className: className,
			value: props.attributes[key],
			allowedFormats: FORMATS,
			placeholder: placeholder,
			onChange: function (v) {
				var o = {};
				o[key] = v;
				props.setAttributes(o);
			}
		});
	}

	function text(props, key, label, help) {
		return el(c.TextControl, {
			label: label,
			help: help,
			value: props.attributes[key] || '',
			onChange: function (v) {
				var o = {};
				o[key] = v;
				props.setAttributes(o);
			}
		});
	}

	function select(props, key, label, options, help) {
		return el(c.SelectControl, {
			label: label,
			help: help,
			value: props.attributes[key],
			options: options,
			onChange: function (v) {
				var o = {};
				o[key] = v;
				props.setAttributes(o);
			}
		});
	}

	function toggle(props, key, label, help) {
		return el(c.ToggleControl, {
			label: label,
			help: help,
			checked: !!props.attributes[key],
			onChange: function (v) {
				var o = {};
				o[key] = v;
				props.setAttributes(o);
			}
		});
	}

	function iconPicker(props, key, label) {
		var opts = Object.keys(ICONS).map(function (n) {
			return { label: n, value: n };
		});
		return el(
			'div',
			{ className: 'kh-icon-picker' },
			el(c.SelectControl, {
				label: label || 'Biểu tượng',
				value: props.attributes[key],
				options: opts,
				onChange: function (v) {
					var o = {};
					o[key] = v;
					props.setAttributes(o);
				}
			}),
			el('span', { style: { display: 'inline-flex', color: '#157A4F' } }, Icon(props.attributes[key], 24))
		);
	}

	function imagePicker(props, label, prefix) {
		prefix = prefix || 'image';
		var a = props.attributes;
		var url = a[prefix + 'Url'];
		return el(
			MediaUploadCheck,
			null,
			el(MediaUpload, {
				allowedTypes: ['image'],
				value: a[prefix + 'Id'],
				onSelect: function (m) {
					var o = {};
					o[prefix + 'Id'] = m.id;
					o[prefix + 'Url'] = (m.sizes && m.sizes.large ? m.sizes.large.url : m.url);
					o[prefix + 'Alt'] = m.alt || a[prefix + 'Alt'] || '';
					props.setAttributes(o);
				},
				render: function (obj) {
					return el(
						'div',
						{ className: 'kh-image-picker', style: { marginBottom: '16px' } },
						el('p', { style: { fontWeight: 600, margin: '0 0 8px' } }, label || 'Ảnh'),
						url ? el('img', { src: url, alt: '', style: { maxWidth: '100%', borderRadius: '8px', display: 'block', marginBottom: '8px' } }) : null,
						el(c.Button, { variant: 'secondary', onClick: obj.open }, url ? 'Đổi ảnh' : 'Chọn ảnh'),
						url
							? el(c.Button, {
								variant: 'link',
								isDestructive: true,
								style: { marginLeft: '8px' },
								onClick: function () {
									var o = {};
									o[prefix + 'Id'] = 0;
									o[prefix + 'Url'] = '';
									props.setAttributes(o);
								}
							}, 'Xóa ảnh')
							: null,
						el(c.TextControl, {
							label: 'Mô tả ảnh (alt – tốt cho SEO)',
							value: a[prefix + 'Alt'] || '',
							onChange: function (v) {
								var o = {};
								o[prefix + 'Alt'] = v;
								props.setAttributes(o);
							}
						})
					);
				}
			})
		);
	}

	function panel(title, children, open) {
		return el.apply(null, [c.PanelBody, { title: title, initialOpen: open !== false }].concat(children.filter(Boolean)));
	}

	function inspector() {
		return el.apply(null, [InspectorControls, null].concat(Array.prototype.slice.call(arguments)));
	}

	function anchorField(props) {
		return text(props, 'anchor', 'ID neo (để menu cuộn tới)', 'Ví dụ: khoa-hoc → link "#khoa-hoc" sẽ cuộn tới khối này.');
	}

	var saveInner = function () {
		return el(InnerBlocks.Content);
	};
	var saveNull = function () {
		return null;
	};

	var BG = [
		{ label: 'Nền trắng', value: 'white' },
		{ label: 'Nền xanh nhạt', value: 'soft' }
	];

	/* ---------- Khu vực (section) ---------- */

	registerBlockType('kh/section', {
		apiVersion: 3,
		title: 'Khu vực nội dung',
		description: 'Một khu vực có tiêu đề nhỏ, tiêu đề lớn, mô tả. Kéo thả khối khác vào bên trong. Bôi đậm chữ trong tiêu đề để làm nổi bật.',
		category: CAT,
		icon: 'align-wide',
		keywords: ['section', 'khu vuc', 'tieu de'],
		supports: { html: false, className: true },
		attributes: {
			eyebrow: str(),
			title: str(),
			text: str(),
			footnote: str(),
			bg: str('white'),
			align: str('center'),
			anchor: str()
		},
		edit: function (props) {
			var a = props.attributes;
			var bp = useBlockProps({ className: 'kh-section kh-bg-' + a.bg + ' kh-align-' + a.align });
			return el(
				Fragment,
				null,
				inspector(
					panel('Cài đặt khu vực', [
						select(props, 'bg', 'Màu nền', BG),
						select(props, 'align', 'Căn tiêu đề', [
							{ label: 'Giữa', value: 'center' },
							{ label: 'Trái', value: 'left' }
						]),
						anchorField(props)
					])
				),
				el(
					'section',
					bp,
					el(
						'div',
						{ className: 'kh-container' },
						el(
							'div',
							{ className: 'kh-section-box' },
							el(
								'header',
								{ className: 'kh-section-head' },
								rt(props, 'eyebrow', 'p', 'Tiêu đề nhỏ…', 'kh-eyebrow'),
								rt(props, 'title', 'h2', 'Tiêu đề khu vực (bôi đậm để tô nổi bật)…', 'kh-h2'),
								rt(props, 'text', 'p', 'Mô tả ngắn…', 'kh-lead')
							),
							el('div', { className: 'kh-section-body' }, el(InnerBlocks, { templateLock: false })),
							rt(props, 'footnote', 'p', 'Dòng ghi chú cuối (không bắt buộc)…', 'kh-footnote')
						)
					)
				)
			);
		},
		save: saveInner
	});

	/* ---------- Hero ---------- */

	registerBlockType('kh/hero', {
		apiVersion: 3,
		title: 'Banner đầu trang (Hero)',
		description: 'Khối mở đầu trang chủ: tiêu đề lớn, mô tả, nút, ảnh và 2 nhãn nổi.',
		category: CAT,
		icon: 'cover-image',
		supports: { html: false, className: true, multiple: false },
		attributes: {
			badge: str('Mới'),
			badgeText: str(),
			title: str(),
			text: str(),
			imageId: { type: 'number', default: 0 },
			imageUrl: str(),
			imageAlt: str(),
			formula: str(),
			chip1Value: str(),
			chip1Label: str(),
			chip1Icon: str('users'),
			chip2Value: str(),
			chip2Label: str(),
			chip2Icon: str('table'),
			anchor: str()
		},
		edit: function (props) {
			var a = props.attributes;
			var bp = useBlockProps({ className: 'kh-hero' });
			var chip = function (n) {
				return a['chip' + n + 'Value']
					? el('div', { className: 'kh-chip kh-chip-' + n }, el('span', { className: 'kh-chip-icon' }, Icon(a['chip' + n + 'Icon'], 18)), el('span', null, el('strong', null, a['chip' + n + 'Value']), el('small', null, a['chip' + n + 'Label'])))
					: null;
			};
			return el(
				Fragment,
				null,
				inspector(
					panel('Ảnh', [imagePicker(props, 'Ảnh chính (dọc, khoảng 683×1024)')]),
					panel('Nhãn trên đầu', [text(props, 'badge', 'Chữ trong ô màu (ví dụ: Mới)')]),
					panel('Thanh công thức trên ảnh', [text(props, 'formula', 'Nội dung', 'Ví dụ: =HỌC_ĐÚNG_CÁCH(Excel; AI). Để trống để ẩn.')], false),
					panel('Nhãn nổi trên ảnh', [
						text(props, 'chip1Value', 'Nhãn 1 – số/chữ đậm'),
						text(props, 'chip1Label', 'Nhãn 1 – dòng nhỏ'),
						iconPicker(props, 'chip1Icon', 'Nhãn 1 – biểu tượng'),
						text(props, 'chip2Value', 'Nhãn 2 – số/chữ đậm'),
						text(props, 'chip2Label', 'Nhãn 2 – dòng nhỏ'),
						iconPicker(props, 'chip2Icon', 'Nhãn 2 – biểu tượng')
					], false),
					panel('Nâng cao', [anchorField(props)], false)
				),
				el(
					'section',
					bp,
					el(
						'div',
						{ className: 'kh-container' },
						el(
							'div',
							{ className: 'kh-hero-box' },
							el(
								'div',
								{ className: 'kh-hero-copy' },
								el('p', { className: 'kh-pill-note' }, a.badge ? el('span', null, a.badge) : null, rt(props, 'badgeText', 'span', 'Dòng thông báo nhỏ…')),
								rt(props, 'title', 'h1', 'Tiêu đề lớn (bôi đậm để tô nổi bật)…', 'kh-h1'),
								rt(props, 'text', 'p', 'Mô tả ngắn về khóa học…', 'kh-hero-text'),
								el(
									'div',
									{ className: 'kh-hero-inner' },
									el(InnerBlocks, {
										allowedBlocks: ['core/buttons', 'core/list', 'core/paragraph'],
										template: [
											['core/buttons', {}, [
												['core/button', { text: 'Xem các khóa học', url: '#khoa-hoc' }],
												['core/button', { text: 'Tư vấn qua Zalo', className: 'is-style-outline' }]
											]],
											['core/list', { className: 'kh-checklist' }, [
												['core/list-item', { content: 'Học trọn đời' }],
												['core/list-item', { content: 'Nhóm hỗ trợ không thời hạn' }]
											]]
										]
									})
								)
							),
							el(
								'div',
								{ className: 'kh-hero-media' },
								el(
									'figure',
									{ className: 'kh-hero-frame' },
									a.formula ? el('div', { className: 'kh-formula' }, el('span', null, 'fx'), el('code', null, a.formula)) : null,
									a.imageUrl ? el('img', { className: 'kh-hero-img', src: a.imageUrl, alt: '' }) : el('div', { className: 'kh-hero-img kh-img-empty' }, 'Chọn ảnh ở cột bên phải →')
								),
								chip(1),
								chip(2)
							)
						)
					)
				)
			);
		},
		save: saveInner
	});

	/* ---------- Stats ---------- */

	registerBlockType('kh/stats', {
		apiVersion: 3,
		title: 'Dãy số liệu',
		description: 'Các con số nổi bật (học viên, người theo dõi…). Số sẽ chạy lên khi cuộn tới.',
		category: CAT,
		icon: 'chart-bar',
		supports: { html: false },
		attributes: { variant: str('bar') },
		edit: function (props) {
			var bp = useBlockProps();
			var cls = props.attributes.variant === 'compact' ? 'kh-stats kh-stats-compact' : 'kh-stats kh-stats-bar';
			return el(
				Fragment,
				null,
				inspector(panel('Kiểu hiển thị', [select(props, 'variant', 'Kiểu', [
					{ label: 'Dải ngang lớn (dưới banner)', value: 'bar' },
					{ label: 'Ô nhỏ (trong khối Giới thiệu)', value: 'compact' }
				])])),
				el('div', bp, el('div', { className: cls }, el(InnerBlocks, {
					allowedBlocks: ['kh/stat'],
					orientation: 'horizontal',
					template: [['kh/stat'], ['kh/stat'], ['kh/stat']]
				})))
			);
		},
		save: saveInner
	});

	registerBlockType('kh/stat', {
		apiVersion: 3,
		title: 'Số liệu',
		category: CAT,
		icon: 'chart-line',
		parent: ['kh/stats'],
		supports: { html: false },
		attributes: { value: str(), label: str() },
		edit: function (props) {
			var bp = useBlockProps({ className: 'kh-stat' });
			return el(
				'div',
				bp,
				el(RichText, { tagName: 'strong', className: 'kh-stat-value', value: props.attributes.value, allowedFormats: [], placeholder: '1.000+', onChange: function (v) { props.setAttributes({ value: v }); } }),
				el(RichText, { tagName: 'span', className: 'kh-stat-label', value: props.attributes.label, allowedFormats: [], placeholder: 'Mô tả số liệu', onChange: function (v) { props.setAttributes({ label: v }); } })
			);
		},
		save: saveNull
	});

	/* ---------- Grid + Card ---------- */

	registerBlockType('kh/grid', {
		apiVersion: 3,
		title: 'Lưới thẻ',
		description: 'Lưới 2–4 cột chứa các thẻ. Mỗi thẻ chọn được kiểu: vấn đề, tính năng, bước, ảnh, liên kết, gợi ý.',
		category: CAT,
		icon: 'grid-view',
		supports: { html: false, className: true },
		attributes: { columns: { type: 'number', default: 3 }, title: str(), boxed: { type: 'boolean', default: false } },
		edit: function (props) {
			var a = props.attributes;
			var bp = useBlockProps();
			var grid = el('div', { className: 'kh-grid kh-cols-' + a.columns }, el(InnerBlocks, {
				allowedBlocks: ['kh/card'],
				orientation: 'horizontal',
				template: [['kh/card'], ['kh/card'], ['kh/card']]
			}));
			return el(
				Fragment,
				null,
				inspector(panel('Lưới', [
					el(c.RangeControl, { label: 'Số cột (máy tính)', min: 1, max: 4, value: a.columns, onChange: function (v) { props.setAttributes({ columns: v }); } }),
					toggle(props, 'boxed', 'Đặt trong khung viền (kiểu "Chưa biết chọn khóa nào?")')
				])),
				el('div', bp, el('div', { className: a.boxed ? 'kh-panel' : 'kh-grid-wrap' }, rt(props, 'title', 'h3', 'Tiêu đề lưới (không bắt buộc)…', 'kh-grid-title'), grid))
			);
		},
		save: saveInner
	});

	var CARD_VARIANTS = [
		{ label: 'Tính năng (biểu tượng + tiêu đề + mô tả)', value: 'feature' },
		{ label: 'Vấn đề (số thứ tự + tiêu đề)', value: 'problem' },
		{ label: 'Bước lộ trình', value: 'step' },
		{ label: 'Ảnh + tiêu đề', value: 'image' },
		{ label: 'Liên kết (mạng xã hội, nhóm)', value: 'link' },
		{ label: 'Gợi ý chọn khóa', value: 'choice' }
	];

	registerBlockType('kh/card', {
		apiVersion: 3,
		title: 'Thẻ',
		category: CAT,
		icon: 'index-card',
		parent: ['kh/grid'],
		supports: { html: false, className: true },
		attributes: {
			variant: str('feature'),
			icon: str('sparkles'),
			number: str(),
			title: str(),
			text: str(),
			meta: str(),
			meta2: str('Bước'),
			url: str(),
			imageId: { type: 'number', default: 0 },
			imageUrl: str(),
			imageAlt: str()
		},
		edit: function (props) {
			var a = props.attributes;
			var v = a.variant;
			var bp = useBlockProps({ className: 'kh-card kh-card-' + v });
			var title = rt(props, 'title', 'h3', 'Tiêu đề thẻ…', 'kh-card-title');
			var body = rt(props, 'text', 'p', 'Mô tả…', 'kh-card-text');
			var inner;
			if (v === 'problem') {
				inner = [el('span', { className: 'kh-num', key: 'n' }, a.number || '01'), el('div', { key: 'b' }, title, body)];
			} else if (v === 'step') {
				inner = [el('p', { className: 'kh-step-label', key: 's' }, el('span', null, a.number || '1'), a.meta2), title, body, rt(props, 'meta', 'p', 'Ví dụ: 40 bài · 5 giờ', 'kh-card-meta')];
			} else if (v === 'image') {
				inner = [el('div', { className: 'kh-card-media' + (a.imageUrl ? '' : ' kh-card-media-empty'), key: 'm' }, a.imageUrl ? el('img', { className: 'kh-card-img', src: a.imageUrl, alt: '' }) : null), el('div', { className: 'kh-card-body', key: 'b' }, title, body)];
			} else if (v === 'link') {
				inner = [el('span', { className: 'kh-icon-box', key: 'i' }, Icon(a.icon)), el('div', { className: 'kh-card-grow', key: 'b' }, title, body), el('span', { key: 'a', className: 'kh-card-arrow' }, Icon('arrow-up-right', 16))];
			} else if (v === 'choice') {
				inner = [rt(props, 'text', 'span', 'Nhu cầu, ví dụ: Muốn giỏi Excel trước', 'kh-choice-need'), rt(props, 'title', 'strong', 'Gợi ý khóa · giá', 'kh-choice-pick')];
			} else {
				inner = [el('span', { className: 'kh-icon-box', key: 'i' }, Icon(a.icon)), title, body];
			}
			var settings = [select(props, 'variant', 'Kiểu thẻ', CARD_VARIANTS)];
			if (v === 'feature' || v === 'link') settings.push(iconPicker(props, 'icon'));
			if (v === 'problem' || v === 'step') settings.push(text(props, 'number', 'Số thứ tự'));
			if (v === 'step') settings.push(text(props, 'meta2', 'Chữ cạnh số (ví dụ: Bước)'));
			if (v === 'image') settings.push(imagePicker(props, 'Ảnh thẻ (ngang 16:9)'));
			settings.push(text(props, 'url', 'Link khi bấm vào thẻ (không bắt buộc)'));
			return el(Fragment, null, inspector(panel('Thẻ', settings)), el('div', bp, inner));
		},
		save: saveNull
	});

	/* ---------- About ---------- */

	registerBlockType('kh/about', {
		apiVersion: 3,
		title: 'Giới thiệu (ảnh + câu trích)',
		description: 'Ảnh người dạy/thương hiệu bên trái, câu trích dẫn, nội dung và số liệu bên phải.',
		category: CAT,
		icon: 'id',
		supports: { html: false, className: true },
		attributes: {
			eyebrow: str(),
			title: str(),
			quote: str(),
			quoteAuthor: str(),
			imageId: { type: 'number', default: 0 },
			imageUrl: str(),
			imageAlt: str(),
			bg: str('soft'),
			anchor: str()
		},
		edit: function (props) {
			var a = props.attributes;
			var bp = useBlockProps({ className: 'kh-section kh-bg-' + a.bg });
			return el(
				Fragment,
				null,
				inspector(
					panel('Ảnh', [imagePicker(props, 'Ảnh (dọc)')]),
					panel('Cài đặt', [text(props, 'quoteAuthor', 'Tên người nói câu trích'), select(props, 'bg', 'Màu nền', BG), anchorField(props)])
				),
				el('section', bp, el('div', { className: 'kh-container' }, el('div', { className: 'kh-section-box' }, el(
					'div',
					{ className: 'kh-about' },
					el(
						'div',
						{ className: 'kh-about-media' },
						a.imageUrl ? el('img', { className: 'kh-about-img', src: a.imageUrl, alt: '' }) : el('div', { className: 'kh-about-img kh-img-empty' }, 'Chọn ảnh ở cột phải →'),
						el('blockquote', { className: 'kh-quote' }, rt(props, 'quote', 'p', '“Câu trích dẫn…”'), a.quoteAuthor ? el('cite', null, a.quoteAuthor) : null)
					),
					el(
						'div',
						{ className: 'kh-about-copy' },
						rt(props, 'eyebrow', 'p', 'Tiêu đề nhỏ…', 'kh-eyebrow'),
						rt(props, 'title', 'h2', 'Tiêu đề…', 'kh-h2'),
						el('div', { className: 'kh-prose' }, el(InnerBlocks, {
							allowedBlocks: ['core/paragraph', 'core/list', 'core/heading', 'kh/stats', 'core/buttons'],
							template: [['core/paragraph', { placeholder: 'Viết đoạn giới thiệu…' }], ['kh/stats', { variant: 'compact' }]]
						}))
					)
				))))
			);
		},
		save: saveInner
	});

	/* ---------- Courses (dynamic list) ---------- */

	registerBlockType('kh/courses', {
		apiVersion: 3,
		title: 'Danh sách khóa học',
		description: 'Tự lấy từ mục "Khóa học" trong menu quản trị. Sửa giá/nội dung ở đó, trang chủ tự cập nhật.',
		category: CAT,
		icon: 'welcome-learn-more',
		supports: { html: false },
		attributes: {
			layout: str('featured'),
			count: { type: 'number', default: 2 },
			category: str(),
			title: str(),
			onlyOthers: { type: 'boolean', default: true }
		},
		edit: function (props) {
			var a = props.attributes;
			var bp = useBlockProps();
			return el(
				Fragment,
				null,
				inspector(panel('Hiển thị', [
					select(props, 'layout', 'Kiểu', [
						{ label: 'Thẻ lớn (khóa nổi bật)', value: 'featured' },
						{ label: 'Lưới thẻ nhỏ', value: 'grid' }
					]),
					el(c.RangeControl, { label: 'Số khóa tối đa', min: 1, max: 24, value: a.count, onChange: function (v) { props.setAttributes({ count: v }); } }),
					select(props, 'category', 'Danh mục', DATA.categories || [{ label: 'Tất cả', value: '' }]),
					a.layout === 'grid' ? toggle(props, 'onlyOthers', 'Ẩn khóa nổi bật (tránh lặp)') : null,
					text(props, 'title', 'Tiêu đề phía trên (không bắt buộc)')
				])),
				el('div', bp, SSR ? el(SSR, { block: 'kh/courses', attributes: a }) : el('p', { className: 'kh-empty' }, 'Danh sách khóa học sẽ hiện ở đây.'))
			);
		},
		save: saveNull
	});

	/* ---------- Masonry images ---------- */

	registerBlockType('kh/masonry', {
		apiVersion: 3,
		title: 'Ảnh phản hồi học viên',
		description: 'Lưới ảnh so le (ảnh chụp tin nhắn, nhận xét).',
		category: CAT,
		icon: 'format-gallery',
		supports: { html: false },
		attributes: { images: { type: 'array', default: [] } },
		edit: function (props) {
			var imgs = props.attributes.images || [];
			var bp = useBlockProps();
			var picker = el(MediaUploadCheck, null, el(MediaUpload, {
				allowedTypes: ['image'],
				multiple: true,
				gallery: true,
				value: imgs.map(function (i) { return i.id; }),
				onSelect: function (list) {
					props.setAttributes({
						images: list.map(function (m) {
							return { id: m.id, url: (m.sizes && m.sizes.large ? m.sizes.large.url : m.url), alt: m.alt || '' };
						})
					});
				},
				render: function (o) {
					return el(c.Button, { variant: 'primary', onClick: o.open }, imgs.length ? 'Thêm / bớt / sắp xếp ảnh' : 'Chọn ảnh');
				}
			}));
			return el(
				Fragment,
				null,
				inspector(panel('Ảnh', [picker])),
				el('div', bp, imgs.length
					? el('div', { className: 'kh-masonry' }, imgs.map(function (i) {
						return el('figure', { className: 'kh-masonry-item', key: i.id || i.url }, el('img', { src: i.url, alt: '' }));
					}))
					: el('div', { className: 'kh-img-empty', style: { minHeight: '160px' } }, picker))
			);
		},
		save: saveNull
	});

	/* ---------- FAQ ---------- */

	registerBlockType('kh/faq', {
		apiVersion: 3,
		title: 'Câu hỏi thường gặp',
		description: 'Danh sách hỏi – đáp dạng xổ xuống. Tự tạo dữ liệu FAQ cho Google.',
		category: CAT,
		icon: 'editor-help',
		supports: { html: false },
		edit: function () {
			return el('div', useBlockProps({ className: 'kh-faq' }), el(InnerBlocks, {
				allowedBlocks: ['kh/faq-item'],
				template: [['kh/faq-item'], ['kh/faq-item']]
			}));
		},
		save: saveInner
	});

	registerBlockType('kh/faq-item', {
		apiVersion: 3,
		title: 'Câu hỏi',
		category: CAT,
		icon: 'editor-help',
		parent: ['kh/faq'],
		supports: { html: false },
		attributes: { question: str(), answer: str() },
		edit: function (props) {
			return el(
				'div',
				useBlockProps({ className: 'kh-faq-item is-editing' }),
				rt(props, 'question', 'p', 'Câu hỏi…', 'kh-faq-q'),
				rt(props, 'answer', 'p', 'Câu trả lời…', 'kh-faq-answer')
			);
		},
		save: saveNull
	});

	/* ---------- CTA ---------- */

	registerBlockType('kh/cta', {
		apiVersion: 3,
		title: 'Kêu gọi hành động (nền đậm)',
		description: 'Khối nền đậm cuối trang. Hotline và email tự lấy từ "Cài đặt website".',
		category: CAT,
		icon: 'megaphone',
		supports: { html: false, className: true },
		attributes: { title: str(), text: str(), showContacts: { type: 'boolean', default: true }, anchor: str() },
		edit: function (props) {
			var a = props.attributes;
			var contact = function (icon, label, val) {
				return val ? el('span', { className: 'kh-cta-contact' }, el('span', null, Icon(icon, 18)), el('span', null, el('small', null, label), val)) : null;
			};
			return el(
				Fragment,
				null,
				inspector(panel('Cài đặt', [toggle(props, 'showContacts', 'Hiện Hotline & Email', 'Sửa số/email trong menu "Cài đặt website".'), anchorField(props)])),
				el('section', useBlockProps({ className: 'kh-cta' }), el('div', { className: 'kh-container' }, el(
					'div',
					{ className: 'kh-cta-box' },
					el(
						'div',
						{ className: 'kh-cta-copy' },
						rt(props, 'title', 'h2', 'Tiêu đề kêu gọi…', 'kh-h2'),
						rt(props, 'text', 'p', 'Mô tả ngắn…'),
						el('div', { className: 'kh-cta-actions' }, el(InnerBlocks, {
							allowedBlocks: ['core/buttons'],
							template: [['core/buttons', {}, [
								['core/button', { text: 'Xem các khóa học', url: '#khoa-hoc' }],
								['core/button', { text: 'Nhắn Zalo tư vấn', className: 'is-style-outline' }]
							]]]
						}))
					),
					a.showContacts ? el('div', { className: 'kh-cta-contacts' }, contact('phone', 'Hotline / Zalo', DATA.phone), contact('mail', 'Email', DATA.email)) : null
				)))
			);
		},
		save: saveInner
	});

	/* ---------- Contact form ---------- */

	registerBlockType('kh/contact-form', {
		apiVersion: 3,
		title: 'Form liên hệ / đăng ký tư vấn',
		description: 'Khách điền form → lưu vào mục "Liên hệ" trong quản trị và gửi email báo cho bạn.',
		category: CAT,
		icon: 'email',
		supports: { html: false },
		attributes: {
			title: str('Để lại thông tin, chúng tôi gọi lại trong 24 giờ'),
			text: str(),
			buttonText: str('Gửi thông tin'),
			topics: str('Excel, Word, AI cho công việc, Mua cho công ty')
		},
		edit: function (props) {
			var a = props.attributes;
			var topics = (a.topics || '').split(',').map(function (t) { return t.trim(); }).filter(Boolean);
			return el(
				Fragment,
				null,
				inspector(panel('Form', [
					text(props, 'buttonText', 'Chữ trên nút gửi'),
					text(props, 'topics', 'Lựa chọn "Bạn quan tâm"', 'Ngăn cách bằng dấu phẩy. Để trống để ẩn ô này.')
				])),
				el(
					'div',
					useBlockProps({ className: 'kh-form-card' }),
					rt(props, 'title', 'h3', 'Tiêu đề form…', 'kh-card-title'),
					rt(props, 'text', 'p', 'Mô tả ngắn (không bắt buộc)…', 'kh-card-text'),
					el('div', { className: 'kh-form-grid' },
						el('label', { className: 'kh-field' }, el('span', null, 'Họ và tên *'), el('input', { type: 'text', disabled: true })),
						el('label', { className: 'kh-field' }, el('span', null, 'Số điện thoại *'), el('input', { type: 'text', disabled: true })),
						el('label', { className: 'kh-field' }, el('span', null, 'Email'), el('input', { type: 'text', disabled: true })),
						topics.length ? el('label', { className: 'kh-field' }, el('span', null, 'Bạn quan tâm'), el('select', { disabled: true }, topics.map(function (t) { return el('option', { key: t }, t); }))) : null,
						el('label', { className: 'kh-field kh-field-full' }, el('span', null, 'Lời nhắn'), el('textarea', { rows: 3, disabled: true }))
					),
					el('span', { className: 'kh-btn kh-btn-primary' }, a.buttonText)
				)
			);
		},
		save: saveNull
	});

	/* ---------- Curriculum ---------- */

	registerBlockType('kh/chapter', {
		apiVersion: 3,
		title: 'Chương học',
		description: 'Một chương trong giáo trình. Thêm các "Bài học" bên trong.',
		category: CAT,
		icon: 'list-view',
		supports: { html: false },
		attributes: { title: str(), open: { type: 'boolean', default: false } },
		edit: function (props) {
			return el(
				Fragment,
				null,
				inspector(panel('Chương', [toggle(props, 'open', 'Mở sẵn khi tải trang')])),
				el(
					'div',
					useBlockProps({ className: 'kh-chapter is-editing' }),
					el('div', { className: 'kh-chapter-head' }, rt(props, 'title', 'p', 'Tên chương…', 'kh-chapter-title')),
					el('div', { className: 'kh-lessons' }, el(InnerBlocks, { allowedBlocks: ['kh/lesson'], template: [['kh/lesson']] }))
				)
			);
		},
		save: saveInner
	});

	registerBlockType('kh/lesson', {
		apiVersion: 3,
		title: 'Bài học',
		category: CAT,
		icon: 'video-alt3',
		parent: ['kh/chapter'],
		supports: { html: false },
		attributes: { title: str(), duration: str(), url: str(), type: str('video'), video: str(), free: { type: 'boolean', default: false } },
		edit: function (props) {
			var a = props.attributes;
			// Bản cũ dùng "Link học thử" (url): chuyển sang ô video mới, vẫn giữ là bài học thử.
			var src = a.video || a.url;
			var free = a.free || (!a.video && !!a.url);
			return el(
				Fragment,
				null,
				inspector(panel('Bài học', [
					select(props, 'type', 'Loại', [{ label: 'Video', value: 'video' }, { label: 'Tài liệu', value: 'doc' }]),
					el(c.TextareaControl, {
						key: 'video',
						label: a.type === 'doc' ? 'Link tài liệu' : 'Link video bài học',
						help: 'Dán link YouTube (nên để chế độ "Không công khai"), Vimeo, Google Drive, Bunny Stream, file .mp4 hoặc mã nhúng <iframe>. Chỉ học viên đã mua (hoặc bài học thử) mới thấy link này.',
						value: src,
						rows: 2,
						onChange: function (v) { props.setAttributes(a.url ? { video: v, url: '', free: true } : { video: v }); }
					}),
					el(c.ToggleControl, {
						key: 'free',
						label: 'Cho học thử miễn phí',
						help: 'Học viên đăng nhập (chưa mua) được xem bài này. Bài đầu tiên của khóa luôn được học thử.',
						checked: free,
						onChange: function (v) { props.setAttributes(a.url && !a.video ? { video: a.url, url: '', free: v } : { free: v }); }
					})
				])),
				el(
					'div',
					useBlockProps({ className: 'kh-lesson' }),
					el('span', { className: 'kh-lesson-icon' }, Icon(a.type === 'doc' ? 'file-text' : (free ? 'play' : 'lock'), 16)),
					rt(props, 'title', 'span', 'Tên bài học…', 'kh-lesson-title'),
					free ? el('span', { className: 'kh-preview' }, 'Học thử') : null,
					!src ? el('span', { className: 'kh-lesson-nosrc' }, 'Chưa có video') : null,
					el(RichText, { tagName: 'span', className: 'kh-lesson-time', value: a.duration, allowedFormats: [], placeholder: '05:30', onChange: function (v) { props.setAttributes({ duration: v }); } })
				)
			);
		},
		save: saveNull
	});
})(window.wp);
