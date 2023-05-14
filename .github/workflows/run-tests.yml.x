name: run-tests

on:
  push:
  schedule:
    - cron: "0 0 * * 0"

jobs:

  laravel-10-on-php-82:

    name: PHP${{ matrix.php }} TB${{ matrix.testbench}} ${{ matrix.os-title }} ${{ matrix.dependency-prefer-title }}
    runs-on: ${{ matrix.os }}
    strategy:
      fail-fast: true
      matrix:
        os: [ubuntu-latest, macos-latest, windows-latest]
        php: [8.2]
        testbench: ["^8.0"]
        phpunit: ["^10.1.0"]
        dependency-prefer: [prefer-stable, prefer-lowest]
        phpunit-config-file: [phpunit.xml.dist]
        include:
          - os: ubuntu-latest
            os-title: ubuntu
          - os: macos-latest
            os-title: macos
          - os: windows-latest
            os-title: win
          - dependency-prefer: prefer-stable
            dependency-prefer-title: stable
          - dependency-prefer: prefer-lowest
            dependency-prefer-title: lowest

    steps:
      - name: Checkout code
        uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: fileinfo
          coverage: none

      - name: Install dependencies (composer)
        run: |
          composer require "orchestra/testbench:${{ matrix.testbench }}" "phpunit/phpunit:${{ matrix.phpunit }}" --dev --no-interaction --no-update
          composer update --${{ matrix.dependency-prefer }} --prefer-dist --no-interaction

      - name: Execute tests
        run: vendor/bin/phpunit --configuration=${{ matrix.phpunit-config-file }} --no-coverage --stop-on-error --stop-on-failure

  laravel-9-on-php-82:

    name: PHP${{ matrix.php }} TB${{ matrix.testbench}} ${{ matrix.os-title }} ${{ matrix.dependency-prefer-title }}
    runs-on: ${{ matrix.os }}
    strategy:
      fail-fast: true
      matrix:
        os: [ubuntu-latest, macos-latest, windows-latest]
        php: [8.2]
        testbench: ["^7.0"]
        phpunit: ["^9.6"]
        dependency-prefer: [prefer-stable, prefer-lowest]
        phpunit-config-file: [phpunit.up-to-9.xml.dist]
        include:
          - os: ubuntu-latest
            os-title: ubuntu
          - os: macos-latest
            os-title: macos
          - os: windows-latest
            os-title: win
          - dependency-prefer: prefer-stable
            dependency-prefer-title: stable
          - dependency-prefer: prefer-lowest
            dependency-prefer-title: lowest

    steps:
      - name: Checkout code
        uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: fileinfo
          coverage: none

      - name: Install dependencies (composer)
        run: |
          composer require "orchestra/testbench:${{ matrix.testbench }}" "phpunit/phpunit:${{ matrix.phpunit }}" --dev --no-interaction --no-update
          composer update --${{ matrix.dependency-prefer }} --prefer-dist --no-interaction

      - name: Execute tests
        run: vendor/bin/phpunit --configuration=${{ matrix.phpunit-config-file }} --no-coverage --stop-on-error --stop-on-failure





  laravel-10-on-php-81:

    name: PHP${{ matrix.php }} TB${{ matrix.testbench}} ${{ matrix.os-title }} ${{ matrix.dependency-prefer-title }}
    runs-on: ${{ matrix.os }}
    strategy:
      fail-fast: true
      matrix:
        os: [ubuntu-latest, macos-latest, windows-latest]
        php: [8.1]
        testbench: ["^8.0"]
        phpunit: ["^10.1.0"]
        dependency-prefer: [prefer-stable, prefer-lowest]
        phpunit-config-file: [phpunit.xml.dist]
        include:
          - os: ubuntu-latest
            os-title: ubuntu
          - os: macos-latest
            os-title: macos
          - os: windows-latest
            os-title: win
          - dependency-prefer: prefer-stable
            dependency-prefer-title: stable
          - dependency-prefer: prefer-lowest
            dependency-prefer-title: lowest

    steps:
      - name: Checkout code
        uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: fileinfo
          coverage: none

      - name: Install dependencies (composer)
        run: |
          composer require "orchestra/testbench:${{ matrix.testbench }}" "phpunit/phpunit:${{ matrix.phpunit }}" --dev --no-interaction --no-update
          composer update --${{ matrix.dependency-prefer }} --prefer-dist --no-interaction

      - name: Execute tests
        run: vendor/bin/phpunit --configuration=${{ matrix.phpunit-config-file }} --no-coverage --stop-on-error --stop-on-failure

  laravel-822-to-9-on-php-81:

    name: PHP${{ matrix.php }} TB${{ matrix.testbench}} ${{ matrix.os-title }} ${{ matrix.dependency-prefer-title }}
    runs-on: ${{ matrix.os }}
    strategy:
      fail-fast: true
      matrix:
        os: [ubuntu-latest, macos-latest, windows-latest]
        php: [8.1]
        testbench: ["^7.0", "^6.22"]
        phpunit: ["^9.5.10"]
        dependency-prefer: [prefer-stable, prefer-lowest]
        phpunit-config-file: [phpunit.up-to-9.xml.dist]
        include:
          - os: ubuntu-latest
            os-title: ubuntu
          - os: macos-latest
            os-title: macos
          - os: windows-latest
            os-title: win
          - dependency-prefer: prefer-stable
            dependency-prefer-title: stable
          - dependency-prefer: prefer-lowest
            dependency-prefer-title: lowest

    steps:
      - name: Checkout code
        uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: fileinfo
          coverage: none

      - name: Install dependencies (composer)
        run: |
          composer require "orchestra/testbench:${{ matrix.testbench }}" "phpunit/phpunit:${{ matrix.phpunit }}" --dev --no-interaction --no-update
          composer update --${{ matrix.dependency-prefer }} --prefer-dist --no-interaction

      - name: Execute tests
        run: vendor/bin/phpunit --configuration=${{ matrix.phpunit-config-file }} --no-coverage --stop-on-error --stop-on-failure





  laravel-812-to-9-on-php-80:

    name: PHP${{ matrix.php }} TB${{ matrix.testbench}} ${{ matrix.os-title }} ${{ matrix.dependency-prefer-title }}
    runs-on: ${{ matrix.os }}
    strategy:
      fail-fast: true
      matrix:
        os: [ubuntu-latest, macos-latest, windows-latest]
        php: ["8.0"]
        testbench: ["^7.0", "^6.12"]
        phpunit: ["^9.5.10"]
        dependency-prefer: [prefer-stable, prefer-lowest]
        phpunit-config-file: [phpunit.up-to-9.xml.dist]
        include:
          - os: ubuntu-latest
            os-title: ubuntu
          - os: macos-latest
            os-title: macos
          - os: windows-latest
            os-title: win
          - dependency-prefer: prefer-stable
            dependency-prefer-title: stable
          - dependency-prefer: prefer-lowest
            dependency-prefer-title: lowest

    steps:
      - name: Checkout code
        uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: fileinfo
          coverage: none

      - name: Install dependencies (composer)
        run: |
          composer require "orchestra/testbench:${{ matrix.testbench }}" "phpunit/phpunit:${{ matrix.phpunit }}" --dev --no-interaction --no-update
          composer update --${{ matrix.dependency-prefer }} --prefer-dist --no-interaction

      - name: Execute tests
        run: vendor/bin/phpunit --configuration=${{ matrix.phpunit-config-file }} --no-coverage --stop-on-error --stop-on-failure
