(function ($) {
	var cfg = window.CitationGeneratorConfig || {};
	var busy = false;

	function csrfName() {
		return cfg.csrfName || 'csrf_test_name';
	}

	function csrf() {
		return $('input[name="' + csrfName() + '"]').val() || '';
	}

	function setCsrf(token) {
		if (token) {
			$('input[name="' + csrfName() + '"]').val(token);
		}
	}

	function style() {
		return $('#citation-style').val() || 'apa';
	}

	function showError(message) {
		var alert = document.getElementById('citation-alert');
		alert.textContent = message || 'Something went wrong. Please try again.';
		alert.hidden = false;
	}

	function clearError() {
		var alert = document.getElementById('citation-alert');
		alert.textContent = '';
		alert.hidden = true;
	}

	function setBusy(on) {
		busy = on;
		$('#citation-generate, #citation-reformat, .citation-select').prop('disabled', on);
	}

	function looksLikeIdentifier(value) {
		var text = $.trim(value);
		if (/^https?:\/\//i.test(text)) {
			return true;
		}
		if (/^(?:doi:\s*)?10\.\d{4,9}\/\S+$/i.test(text)) {
			return true;
		}
		var compact = text.replace(/^(?:isbn(?:-1[03])?\s*:?\s*)/i, '').replace(/[\s-]/g, '');
		return /^[0-9]{9}[0-9Xx]$/.test(compact) || /^[0-9]{13}$/.test(compact);
	}

	function post(url, data) {
		data[csrfName()] = csrf();
		data.style = data.style || style();
		setBusy(true);
		return $.ajax({
			url: url,
			type: 'post',
			dataType: 'json',
			data: data
		}).done(function (res) {
			setCsrf(res && res.csrf);
			handle(res || {});
		}).fail(function (xhr) {
			var res = xhr.responseJSON || {};
			setCsrf(res.csrf);
			showError(res.message || 'Something went wrong. Please try again.');
		}).always(function () {
			setBusy(false);
		});
	}

	function handle(res) {
		if (!res.ok) {
			showError(res.message);
			return;
		}
		clearError();
		if (res.mode === 'results') {
			renderResults(res);
			return;
		}
		if (res.mode === 'citation') {
			renderCitation(res);
		}
	}

	function renderResults(res) {
		$('#citation-output').attr('hidden', true);
		var box = document.getElementById('citation-results');
		var list = document.getElementById('citation-matches');
		var message = document.getElementById('citation-results-message');
		list.textContent = '';
		message.textContent = res.message || '';
		(res.results || []).forEach(function (item) {
			var row = document.createElement('div');
			row.className = 'citation-match';

			var title = document.createElement('strong');
			title.textContent = item.title || 'Untitled';
			row.appendChild(title);

			var meta = document.createElement('p');
			var bits = [];
			if (item.authors) {
				bits.push(item.authors);
			}
			if (item.year) {
				bits.push(item.year);
			}
			if (item.venue) {
				bits.push(item.venue);
			}
			meta.textContent = bits.join(' · ');
			row.appendChild(meta);

			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'btn btn-primary citation-select';
			button.textContent = 'Select';
			button.setAttribute('data-provider', item.provider || '');
			button.setAttribute('data-work-id', item.id || '');
			row.appendChild(button);
			list.appendChild(row);
		});
		box.hidden = false;
	}

	function renderCitation(res) {
		$('#citation-results').attr('hidden', true);
		document.getElementById('citation-full').textContent = res.bibliography || '';
		document.getElementById('citation-intext').textContent = res.in_text || '';
		document.getElementById('citation-intext-label').textContent = res.in_text_label || 'In-text citation';

		var record = res.record || {};
		$('#edit-title').val(record.title || '');
		$('#edit-authors').val(res.authors_text || '');
		$('#edit-year').val(record.year || '');
		$('#edit-journal').val(record.journal || '');
		$('#edit-volume').val(record.volume || '');
		$('#edit-issue').val(record.issue || '');
		$('#edit-pages').val(record.pages || '');
		$('#edit-publisher').val(record.publisher || '');
		$('#edit-edition').val(record.edition || '');
		$('#edit-doi').val(record.doi || '');
		$('#edit-isbn').val(record.isbn || '');
		$('#edit-url').val(record.url || '');
		if (record.type) {
			$('#edit-type').val(record.type);
		}

		var warnings = document.getElementById('citation-warnings');
		warnings.textContent = '';
		(res.warnings || []).forEach(function (warning) {
			var item = document.createElement('li');
			item.textContent = warning;
			warnings.appendChild(item);
		});
		warnings.hidden = !(res.warnings && res.warnings.length);
		document.getElementById('citation-output').hidden = false;
	}

	function editPayload() {
		return {
			title: $('#edit-title').val(),
			authors_text: $('#edit-authors').val(),
			year: $('#edit-year').val(),
			journal: $('#edit-journal').val(),
			volume: $('#edit-volume').val(),
			issue: $('#edit-issue').val(),
			pages: $('#edit-pages').val(),
			publisher: $('#edit-publisher').val(),
			edition: $('#edit-edition').val(),
			isbn: $('#edit-isbn').val(),
			doi: $('#edit-doi').val(),
			url: $('#edit-url').val(),
			type: $('#edit-type').val(),
			style: style()
		};
	}

	function copyText(text, button) {
		var done = function () {
			var original = button.textContent;
			button.textContent = 'Copied';
			window.setTimeout(function () {
				button.textContent = original;
			}, 1600);
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done).catch(function () {
				fallbackCopy(text, done);
			});
			return;
		}
		fallbackCopy(text, done);
	}

	function fallbackCopy(text, done) {
		var area = document.createElement('textarea');
		area.value = text;
		area.setAttribute('readonly', 'readonly');
		area.style.position = 'fixed';
		area.style.left = '-9999px';
		document.body.appendChild(area);
		area.select();
		try {
			document.execCommand('copy');
			done();
		} catch (e) {
			showError('Copy failed. Select the citation and copy it manually.');
		}
		document.body.removeChild(area);
	}

	$('#citation-form').on('submit', function (event) {
		event.preventDefault();
		if (busy) {
			return;
		}
		var query = $.trim($('#citation-query').val());
		if (!query) {
			showError('Enter a DOI, ISBN, or title.');
			return;
		}
		clearError();
		var url = looksLikeIdentifier(query) ? cfg.lookupUrl : cfg.searchUrl;
		post(url, { query: query, style: style() });
	});

	$('#citation-edit-form').on('submit', function (event) {
		event.preventDefault();
		if (busy) {
			return;
		}
		post(cfg.formatUrl, editPayload());
	});

	$('#citation-style').on('change', function () {
		if (busy || document.getElementById('citation-output').hidden) {
			return;
		}
		post(cfg.formatUrl, editPayload());
	});

	$('#citation-results').on('click', '.citation-select', function () {
		if (busy) {
			return;
		}
		post(cfg.lookupUrl, {
			provider: $(this).attr('data-provider') || '',
			work_id: $(this).attr('data-work-id') || '',
			query: '',
			style: style()
		});
	});

	$('#copy-full').on('click', function () {
		copyText(document.getElementById('citation-full').textContent || '', this);
	});

	$('#copy-intext').on('click', function () {
		copyText(document.getElementById('citation-intext').textContent || '', this);
	});
}(jQuery));
