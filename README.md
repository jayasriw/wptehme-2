# jPortal Commercial WordPress Job Board

jPortal is a feature-compatible custom WordPress job board and recruitment marketplace inspired by modern commercial job-board themes. It is implemented with original code and contains a theme, a core plugin, and a commercial suite mu-plugin.

## Packages

- `wp-content/plugins/jportal-core` - core job engine: jobs, companies, candidates, applications, saved jobs, alerts, messaging, reviews, plans, analytics, import/export, moderation, and email notifications.
- `wp-content/mu-plugins/jportal-commercial-suite.php` - commercial modules: demo importer, CSV import, payments, paid access controls, GDPR notice, social login integration points, Polylang strings, Elementor widgets, analytics extensions, social sharing, related jobs, resume builder, and layout shortcodes.
- `wp-content/themes/jportal` - responsive professional theme with homepage, job archive, single-job, company, dashboard, submit-job, pricing, header, footer, and mobile UX.
- `wp-content/themes/jportal-child` - child theme starter.
- `docs` - installation, feature matrix, and administrator guide.

## Feature coverage

- Core job board: job listings, marketplace structure, posting, management, featured jobs, related jobs, deadlines, automatic expiration, video descriptions, moderation, applications, resume/CV fields, alerts, notifications, advanced search, taxonomies, import/export, analytics, responsive design, social sharing, multilingual readiness, and GDPR cookie notice.
- Employer/company: company profiles, employer dashboard, candidate management, reviews/ratings, private messaging, video/audio interview URLs, deadline management, and candidate/job administration.
- Candidate: candidate profiles, resume builder, applying to jobs, alerts, messaging, saved jobs, reviews, and interview media support.
- Monetization: subscription plans, payment order foundation, Stripe/PayPal/WooCommerce integration points, featured listing monetization, and paid access quota logic.
- Design: responsive professional UI, layout shortcodes, multiple home/search/detail layout modes, customizable branding, mega-menu-ready navigation, child-theme readiness, and demo setup.
- Integrations: social-login plugin integration points, Polylang string registration, translation-ready code, Elementor widget registration, GDPR notice, and documentation.

## Installation

1. Copy the repo contents into a WordPress installation.
2. Activate `jPortal Core` under Plugins.
3. Ensure `jportal-commercial-suite.php` is present in `wp-content/mu-plugins`.
4. Activate the `jPortal` theme under Appearance > Themes.
5. Go to **jPortal Suite > One-Click Setup** to create pages, menus, starter plans, taxonomies, and sample listings.

## Important

This project does not copy proprietary theme code or assets. It provides original code that implements comparable job-board functionality.