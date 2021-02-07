# berlindb-zlb
An experimental utility library for BerlinDB and WordPress.

## Installation
Install via Composer.

Since Zlb is not currently hosted on a Composer package repository, the GitHub repository should be manually added to your project's `package.json` as such:
```json
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/bosconian-dynamics/berlindb-zlb"
  }
]
```

After the GitHub repository has been added, Zlb can be required as a dependency, following the conventions described in the Composer documentation for [Loading a package from a VCS repository](https://getcomposer.org/doc/05-repositories.md#loading-a-package-from-a-vcs-repository).

For example, to require HEAD from the command line:
```bash
composer require bosconian-dynamics/berlindb-zlb@dev-main
```

or manually in `package.json`:
```json
"require": {
  "bosconian-dynamics/berlindb-zlb": "dev-main"
}
```
