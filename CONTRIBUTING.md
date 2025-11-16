# Contributing to This Project

Thank you for your interest in contributing! By participating, you help improve the project and make it more robust for everyone.

## Getting Started

This project uses **Python 3** with [Poetry](https://python-poetry.org/) for dependency management. It also includes PHP, HTML, Jinja2 templates, and CSS.

### Prerequisites

* Python 3.10+ installed
* [Poetry](https://python-poetry.org/docs/#installation) installed
* PHP (if testing PHP parts)
* A web browser for HTML/CSS

### Installation

1. Clone the repository:

```bash
git clone <repository-url>
cd <repository-folder>
```

2. Install dependencies via Poetry:

```bash
poetry sync
```

3. Activate the virtual environment (optional):

```bash
poetry shell
```
> [!NOTE]  
> Use
> ```bash
> poetry env activate
> ```
> If using a newer Poetry version.



4. Run the main program:

```bash
python3 main.py
```

5. To build for a `prod` enviroment, use:

```bash
python3 main.py --env prod
```

`--env` can be `prod` or `dev`

`dev` will show the build number in the bottom right. For prod builts, i recommend deleting `dist/` first (`rm -rf dist`)



**The whole PHP-website will be 'built' into** `dist/`

Useful command

```bash
python3 main.py && php -S localhost:6001 -t dist/public
```

> [!IMPORTANT]  
> Always serve `dist/public`


## Development Guidelines

* **Directories**
  * All code for CronDNS itself is found in `src/`
  * All code for the Jinja2 build proccess is found in `build/` (and `main.py`)
  * `src/`
    * `lib/` holds Providers and other 'library' functionality
    * `conf/` holds configuration files
    * `public/` holds api, public sites and css
    * `templates/` holds all Jinja2 base templates
* **Code Style**

  * HTML/CSS: Clean, semantic markup; consistent indentation
  * Jinja2: Keep templates readable and DRY

* **Commit Messages**

  * Use clear and descriptive messages
  * Example: `Add user login feature with session support`

* **Branching**

  * Create a branch for each feature or bugfix:

  ```bash
  git checkout -b feature/your-feature-name
  ```

  * Merge back into `main` via pull request


## Submitting Changes

1. Fork the repository
2. Create a feature branch
3. Commit your changes with descriptive messages
4. Push to your fork
5. Open a pull request targeting `main`

**Tip:** Always pull the latest `main` before starting a new feature:

```bash
git checkout main
git pull origin main
```

## Code Review

* Avoid breaking existing functionality
* Keep your code modular and maintainable


## Reporting Issues

* Use the [Issues](https://github.com/TRC-Loop/CronDNS/issues) section to report bugs or request features
* Provide a clear description, steps to reproduce, and relevant code snippets


## Thank You!

Your contributions make this project better for everyone. Every small improvement counts!
