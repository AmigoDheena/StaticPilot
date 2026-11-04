# StaticPilot project page

A single-page, responsive project website. Open `index.html` directly, or visit `http://localhost/StaticPilot/GH-Pages/` with XAMPP running. No build or package installation is required. Fonts load from Google Fonts with local fallback fonts.

The dashboard is an illustrative, browser-only preview. Its tabs switch between sample content. It does not connect to the CMS or expose installation data.

## Publish with GitHub Pages

The repository workflow `.github/workflows/pages.yml` uploads **only `GH-Pages`**, keeping PHP files, credentials, and CMS storage out of the deployed artifact.

1. Commit the website and workflow to the `GH-Pages` branch.
2. In the GitHub repository, open **Settings → Pages → Build and deployment → Source** and select **GitHub Actions**.
3. Push the branch, or run **Deploy project page** from the Actions tab. Allow the `GH-Pages` branch in the `github-pages` environment if its deployment rules require it.
4. Use the URL returned by the deployment. For the current repository, the expected default address is `https://amigodheena.github.io/StaticPilot/`.

The CMS requires a PHP-capable server; GitHub Pages hosts this static project page only. No deployment was performed as part of creating these files.

Workflow reference: https://docs.github.com/en/pages/getting-started-with-github-pages/using-custom-workflows-with-github-pages
