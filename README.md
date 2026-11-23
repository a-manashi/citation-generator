# Citation Generator

A standalone PHP web app for DOI and ISBN lookup, title search, citation formatting, and metadata editing. It includes the original citation engine and its six styles (APA 7, MLA 9, Chicago notes-bibliography, Harvard, IEEE, and Vancouver).

## Run locally

Install PHP 8.1 or later with cURL, intl, and mbstring. From this directory, run:

```powershell
php -S localhost:8080 -t public
```

Open [the citation generator](http://localhost:8080/citation-generator). The home page also opens the generator.

For server deployment, point the document root at `public/` and route non-file requests to `public/index.php`. Give PHP write access to `writable/cache/` and `writable/logs/`. No database is required.

## Configuration

Copy `.env.example` to `.env` to override the defaults. Set `app.baseURL` to the deployed URL, including its trailing slash. Enter your own OpenAlex API key and provider contact email if needed. Keep the key server-side in `.env`.

Metadata lookup uses Crossref, DOI content negotiation, OpenAlex, and Open Library. Live lookups need outbound HTTPS. Formatting an existing record runs locally.

## Checks

Run `php tests/citation/run.php` to run the existing detection, formatting, and provider checks. Live checks depend on provider availability.
