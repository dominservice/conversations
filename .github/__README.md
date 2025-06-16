# GitHub Configuration

This directory contains configuration files for GitHub features.

## Workflows

The `workflows` directory contains GitHub Actions workflow files that automate testing, code quality checks, and deployment processes.

### CI/CD Workflow

The main CI/CD workflow (`ci-cd.yml`) runs on push to main/master branches, on tag pushes, and on pull requests. It includes:

1. **Tests**: Runs PHPUnit tests on multiple PHP versions (8.1, 8.2, 8.3) and Laravel versions (9.*, 10.*, 11.*).
2. **Code Style**: Checks code against PSR-12 standards using PHP_CodeSniffer.
3. **Static Analysis**: Runs PHPStan at level 5 to catch potential bugs and issues.
4. **Release**: Creates a GitHub release when a tag is pushed (only if all previous jobs succeed).

## Configuration Files

The repository root contains these related configuration files:

- `phpunit.xml`: Configuration for PHPUnit tests
- `phpstan.neon`: Configuration for PHPStan static analysis
- `phpcs.xml`: Configuration for PHP_CodeSniffer code style checking

## Usage

GitHub Actions will automatically run the workflows when code is pushed or pull requests are created. You can also view the workflow runs in the "Actions" tab of the GitHub repository.

To create a new release:

1. Update the version number in relevant files
2. Update the CHANGELOG.md file
3. Commit and push the changes
4. Create and push a new tag (e.g., `git tag v1.2.3 && git push origin v1.2.3`)

The CI/CD workflow will automatically create a GitHub release if all tests pass.