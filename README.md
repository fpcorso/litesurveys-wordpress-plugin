# LiteSurveys WordPress Plugin

Add simple one-question surveys to your WordPress site to gather feedback from your visitors. LiteSurveys is a lightweight plugin that helps you create unobtrusive slide-in surveys that appear at the perfect moment.

## Installation

The ideal method is to use the WordPress Plugin Repository to install directly into your WordPress site.

However, you can also manually install from this GitHub repo using the following steps:

1. Download the latest release from the [releases page](https://github.com/fpcorso/litesurveys-wordpress-plugin/releases)
2. Go to your WordPress admin panel
3. Navigate to Plugins > Add New
4. Click "Upload Plugin" and select the downloaded zip file
5. Click "Install Now" and then "Activate"
6. Go to LiteSurveys in your WordPress admin menu to create your first survey

## Development Setup

1. Clone this repository
2. Install PHP dependencies: `composer install`
3. Install Node dependencies: `npm install`
4. Run tests: `composer test`
5. Build assets: `npm run build`

### Requirements

- PHP 8.0 or higher
- WordPress 6.1 or higher
- Node.js 20.x (for development)
- Composer (for development)

## Contributing

Community made feature requests, patches, localizations, bug reports, and contributions are always welcome.

### Creating Issues

* Please [create issues](https://github.com/fpcorso/litesurveys-wordpress-plugin/issues/new) for bugs or feature requests
* For bug reports, please clearly describe the bug/issue and include steps to reproduce
* For feature requests, please clearly describe what you would like, how it would be used, and example screenshots (if possible)

### Pull Requests

* Ensure you stick to the [WordPress Coding Standards](https://codex.wordpress.org/WordPress_Coding_Standards)
* When committing, reference your issue number and include a note about the fix
* Push the changes to your fork and submit a pull request to the `main` branch
* Each function should be documented with phpDoc standards
* Use tabs (not spaces) with a tab indent size of 4

## Deployment

The plugin is automatically deployed to WordPress.org when a new GitHub release is created from the `main` branch. The deployment process:

1. Minifies CSS and JavaScript files
2. Generates translation files
3. Creates a new SVN tag on WordPress.org
4. Updates the plugin assets and readme on WordPress.org

## Testing

The plugin includes comprehensive unit tests. Run them using:

```bash
composer test
```

For development with WordPress multisite, use:

```bash
composer test-multisite
```

## License

This project is licensed under the GPLv3 License - see the [LICENSE](LICENSE) file for details.

## Credits

Developed by [Frank Corso](https://github.com/fpcorso)

See also [the list of contributors](https://github.com/fpcorso/litesurveys-wordpress-plugin/graphs/contributors) who participated in this project.