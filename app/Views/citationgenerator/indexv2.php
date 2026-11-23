<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Free Citation Generator</title>
    <link rel="stylesheet" href="<?= esc(base_url('v2/css/citation-generator.css'), 'attr') ?>">
</head>
<body>
<main>
<section class="section-fluid pd-medium" id="citation-generator">
	<div class="container">
		<div class="title-div center mb20">
			<h1>Free Citation Generator</h1>
		</div>
		<div class="order-box">
			<p class="citation-help">Enter a DOI, ISBN, article or book title. You can also paste a doi.org link. The citation is built from published metadata, and you can correct any missing details before copying it.</p>
			<form id="citation-form" action="<?= esc(site_url('citation-generator'), 'attr') ?>" method="post">
				<?= csrf_field() ?>
				<fieldset>
					<label for="citation-query">DOI, URL, ISBN, or title</label>
					<input type="text" class="form-control cmn-fld" id="citation-query" name="query" maxlength="500" autocomplete="off" placeholder="10.1038/nature12373 or a book title">
				</fieldset>
				<fieldset>
					<label for="citation-style">Citation style</label>
					<select class="form-select form-select-sm cmn-fld" id="citation-style" name="style">
						<?php foreach ($styles as $value => $label) { ?>
						<option value="<?php echo esc($value) ?>"><?php echo esc($label) ?></option>
						<?php } ?>
					</select>
				</fieldset>
				<div class="citation-actions">
					<button type="submit" class="btn btn-primary" id="citation-generate">Generate Citation</button>
				</div>
			</form>

			<div id="citation-alert" class="alert-danger" hidden></div>

			<div id="citation-results" hidden>
				<h2>Matching publications</h2>
				<p id="citation-results-message"></p>
				<div id="citation-matches"></div>
			</div>

			<div id="citation-output" hidden>
				<p class="citation-note">Check this citation before you submit it. Missing details are listed below and can be edited.</p>
				<div class="citation-output-block">
					<h2>Full citation</h2>
					<div id="citation-full" class="citation-text"></div>
					<button type="button" class="btn btn-primary citation-copy" id="copy-full">Copy</button>
				</div>
				<div class="citation-output-block">
					<h2 id="citation-intext-label">In-text citation</h2>
					<div id="citation-intext" class="citation-text"></div>
					<button type="button" class="btn btn-primary citation-copy" id="copy-intext">Copy</button>
				</div>
				<ul id="citation-warnings" class="citation-warnings" hidden></ul>

				<h2>Edit details</h2>
				<form id="citation-edit-form" action="<?= esc(site_url('citation-generator'), 'attr') ?>" method="post">
					<div class="row">
						<div class="col-lg-12">
							<fieldset>
								<label for="edit-title">Title</label>
								<input type="text" class="form-control cmn-fld" id="edit-title" name="title" maxlength="500">
							</fieldset>
						</div>
						<div class="col-lg-12">
							<fieldset>
								<label for="edit-authors">Authors</label>
								<input type="text" class="form-control cmn-fld" id="edit-authors" name="authors_text" maxlength="2000" placeholder="John Smith; Jane Brown">
							</fieldset>
						</div>
						<div class="col-lg-6">
							<fieldset>
								<label for="edit-year">Year</label>
								<input type="text" class="form-control cmn-fld" id="edit-year" name="year" maxlength="4" inputmode="numeric">
							</fieldset>
						</div>
						<div class="col-lg-6">
							<fieldset>
								<label for="edit-type">Source type</label>
								<select class="form-select form-select-sm cmn-fld" id="edit-type" name="type">
									<option value="article">Journal article</option>
									<option value="book">Book</option>
									<option value="other">Other</option>
								</select>
							</fieldset>
						</div>
						<div class="col-lg-12">
							<fieldset>
								<label for="edit-journal">Journal</label>
								<input type="text" class="form-control cmn-fld" id="edit-journal" name="journal" maxlength="300">
							</fieldset>
						</div>
						<div class="col-lg-6">
							<fieldset>
								<label for="edit-publisher">Publisher</label>
								<input type="text" class="form-control cmn-fld" id="edit-publisher" name="publisher" maxlength="200">
							</fieldset>
						</div>
						<div class="col-lg-6">
							<fieldset>
								<label for="edit-edition">Edition</label>
								<input type="text" class="form-control cmn-fld" id="edit-edition" name="edition" maxlength="40">
							</fieldset>
						</div>
						<div class="col-lg-4">
							<fieldset>
								<label for="edit-volume">Volume</label>
								<input type="text" class="form-control cmn-fld" id="edit-volume" name="volume" maxlength="40">
							</fieldset>
						</div>
						<div class="col-lg-4">
							<fieldset>
								<label for="edit-issue">Issue</label>
								<input type="text" class="form-control cmn-fld" id="edit-issue" name="issue" maxlength="40">
							</fieldset>
						</div>
						<div class="col-lg-4">
							<fieldset>
								<label for="edit-pages">Pages</label>
								<input type="text" class="form-control cmn-fld" id="edit-pages" name="pages" maxlength="50">
							</fieldset>
						</div>
						<div class="col-lg-6">
							<fieldset>
								<label for="edit-doi">DOI</label>
								<input type="text" class="form-control cmn-fld" id="edit-doi" name="doi" maxlength="200">
							</fieldset>
						</div>
						<div class="col-lg-6">
							<fieldset>
								<label for="edit-isbn">ISBN</label>
								<input type="text" class="form-control cmn-fld" id="edit-isbn" name="isbn" maxlength="20">
							</fieldset>
						</div>
					</div>
					<input type="hidden" id="edit-url" name="url" value="">
					<div class="citation-actions">
						<button type="submit" class="btn btn-primary" id="citation-reformat">Generate Citation</button>
					</div>
				</form>
			</div>
			<noscript><p>This tool needs JavaScript to generate citations.</p></noscript>
		</div>
	</div>
</section>
</main>
<script>
window.CitationGeneratorConfig = {
	lookupUrl: <?= json_encode(site_url('citation-generator/lookup')) ?>,
	searchUrl: <?= json_encode(site_url('citation-generator/search')) ?>,
	formatUrl: <?= json_encode(site_url('citation-generator/format')) ?>,
	csrfName: <?= json_encode(csrf_token()) ?>
};
</script>
<script src="<?= esc(base_url('v2/js/jquery.min.js'), 'attr') ?>"></script>
<script src="<?= esc(base_url('v2/js/citation-generator.js'), 'attr') ?>"></script>
</body>
</html>
