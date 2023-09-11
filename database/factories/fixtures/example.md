I love Open Source. I love to contribute to Open Source. But sometimes... sometimes... that can be a real pain. I might have a great idea for a contribution, but because getting the package set up locally is going to take considerable time, it just never happens. Perhaps the package has some special requirements such as image manipulation libraries or Ghostscript for PDF work. Or maybe it needs testing on an older version of PHP that I no longer have installed.

We solved this frustration for applications a fair while back now using Docker (and for those of us in the Laravel community: Sail). Docker allows us to take a complex set of application requirements and bundle them up in a distributable file that can be replicated on almost any computer. It doesn't matter if the application you're working on uses PHP 7.4 and you're on 8.2, you can use Docker to install those dependencies and be up and running in a matter of minutes.

For some reason however, this trend never made it to the world of PHP/Laravel packages. That's a shame, because it's incredibly easy to set up and requires close to 0 maintenance. Recently, I've been going through packages I maintain and adding Docker support, so I wanted to take the time to show you how straightforward it is so that you can do it too, and maybe even make some OSS contributions to add it to other packages out in the wild!

## Docker Compose

Docker Compose is a tool that allows us to orchestrate several different Docker services simultaneously. If you've used Laravel Sail, you're actually using Docker Compose with some Otwell DX© sprinkled on top.

For packages, we rarely actually need any long running services, like a PHP web server. What we actually want is access to a few short lived commands, so that we can do things like install composer dependencies and run our test suite. To that end, let's build out a simple docker-compose.yml file that will live at the root of our package.

```yaml
version: "3.8"

services:
  php:
    image: php:8.2-cli-alpine
    working_dir: /var/www/html
    volumes:
      - .:/var/www/html
```

This is the most basic variant of our docker-compose.yml file, and gives those working on your package access to PHP (in this case 8.2, though you're free to change that to suit your requirements) to run things like:

```bash
docker compose run --rm php ./vendor/bin/pest
```

Just to quickly step through that command:

- `docker compose run` tells Docker that we want to create a container
- `--rm` tells Docker that once we're finished, it should destroy the container
- `php` tells Docker that we want to use the `php` service defined in our `docker-compose.yml` file

This is all pretty cool, and will really help developers who have a different version of PHP installed locally, but it's very limited. How do they install composer dependencies for our package, for example?

For that, we're going to have to create our own Dockerfile.

## The Dockerfile

Now, don't panic. When I was first playing around with Docker and heard the word "Dockerfile", I began running for the hills. However, there is no reason to panic, it's actually pretty straightforward. Let's work through an example together, which we will store in `docker/Dockerfile`.

```docker
FROM php:8.2-cli-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENTRYPOINT ["php"]
```

That's it. 4 lines. Wasn't so bad was it? Let's walk through it nice and slowly.

### `FROM php:8.2-cli-alpine`

The first line tells Docker that we want to start from an existing image, built by the PHP team, called `php:8.2-cli-alpine`. You can find more information about the official PHP images [here](https://hub.docker.com/_/php), but here are the basics:

- `php:8.2` tells Docker that we want an image based on PHP 8.2. Surprise!
- `cli` is a variant of the PHP image that doesn't include a web server. We don't need a webserver for our package.
- `alpine` is the Alpine Linux project. It is a lot smaller than distributions like Ubuntu, so if you can get away with using it, you should, especially for packages.

### `COPY --from=composer:2 /usr/bin/composer /usr/bin/composer`

Line two is quite special. We're reaching in to a prebuilt image (created by the team behind the Composer package manager) and pulling out a single file: the composer script binary! We're copying that binary to `/usr/bin/composer`, which will let us run it from anywhere later.

### `WORKDIR /var/www/html`

Line 3 changes the default working directory to the same place we'll be storing the package source code.

### `ENTRYPOINT ["php"]`

Finally, line 4 makes clear that when we execute this container, we want to start from the `php` executable. We can tag things on to that entrypoint to do more exciting things, but that gives us a nice basis.

Alright, now that we have our Dockerfile ready, we can update our docker-compose.yml to match:

```yaml
version: "3.8"

services:
  php:
    build: ./docker
    volumes:
      - .:/var/www/html
```

Note that we now point to our newly created ./docker directory rather than a prebuilt image. We don't need to specify `Dockerfile`, because that is inferred by Docker.

With this in place, we could now run the following to install composer dependencies:

```bash
docker compose run --rm php /usr/bin/composer install
```

## Improving the DX

Seen as we're trying to make developer's lives as simple as possible, why don't we add some aliases in our docker-compose.yml file to make it simpler to run these commands?

Let's add a specific service for Composer.

```yaml
services:
  php:
    build: ./docker
    volumes:
      - .:/var/www/html
  composer: # [tl! focus:start]
    build: ./docker
    entrypoint: ["composer"]
    volumes:
      - .:/var/www/html # [tl! focus:end]
```

Note that the service is almost identical, but we select a different entrypoint. This means that executing composer commands and scripts is even easer:

```bash
docker compose run --rm php /usr/bin/composer install # [tl! --]
docker compose run --rm composer install # [tl! ++]
```

I tend to add scripts for testing, linting and static analysic to my composer.json file, like so:

```json
"scripts": {
    "lint": "vendor/bin/pint",
    "phpstan": "vendor/bin/phpstan analyse",
    "pest": "vendor/bin/pest --parallel"
},
```

If you do this too, that means that executing these scripts from Docker is as simple as:

```bash
docker compose run --rm composer lint
docker compose run --rm composer phpstan
docker compose run --rm composer pest
```

You can of course add as many services to your docker-compose.yml as makes sense for your package. Let's add a specific service that will execute our unit tests.

```yaml
services:
  php:
    build: ./docker
    volumes:
      - .:/var/www/html
  composer:
    build: ./docker
    entrypoint: ["composer"]
    volumes:
      - .:/var/www/html
  pest: # [tl! focus:start]
    build: ./docker
    entrypoint: ["php", "vendor/bin/pest"]
    volumes:
      - .:/var/www/html # [tl! focus:end]
```

Have a guess as to how we'd use this?

```bash
docker compose run --rm pest
```

## Adding support for XDebug

It's always handy to have debugger around, and it's no different when it comes to packages. I find debugging a package can be a really good way to get to know the internals better; drop a break-point on a test, run it and walk through the results. Well, it's incredibly easy to add [XDebug](https://xdebug.org) to our Docker image:

```docker
FROM php:8.2-cli-alpine

RUN apk add --no-cache $PHPIZE_DEPS linux-headers
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENTRYPOINT ["php"]
```

With these 3 lines, we've configured and enabled XDebug. But how do we use it? Let's update our docker-compose.yml file with two environment variables:

```yaml
services:
  php:
    build: ./docker
    volumes:
      - .:/var/www/html
    environment: # [tl! focus:start]
      - XDEBUG_MODE=${XDEBUG_MODE:-off}
      - XDEBUG_CONFIG=${XDEBUG_CONFIG:-client_host=host.docker.internal} # [tl! focus:end]
  composer:
    build: ./docker
    entrypoint: ["composer"]
    volumes:
      - .:/var/www/html
  pest:
    build: ./docker
    entrypoint: ["php", "vendor/bin/pest"]
    volumes:
      - .:/var/www/html
```

Now by default, XDebug will be disabled because the default value of the `XDEBUG_MODE` variable is `off`. However, we can edit that variable at runtime by adding the `-e` flag to our command with the relevant values:

```bash
docker compose run --rm -e XDEBUG_MODE=debug php -v
```

All that's left to do is wire up your PHP service to your IDE so that it can access XDebug. [Here's a guide for PHPStorm](https://www.jetbrains.com/help/phpstorm/configuring-remote-interpreters.html#4911ed2a).

## Wrapping up

So, with just two files and a tiny bit of code, any developer with Docker installed locally can clone our package, run `docker compose run --rm composer install` and immediately be set up to begin contributing.

A few more lines and they have a powerful debugger at their fingertips for getting into the nitty-gritty.

Once their contribution is ready, they can make sure it all works with `docker compose run --rm pest` before creating a PR for us to merge.

And the next time a new version of PHP is released, we can ensure everything works by changing one line in our Dockerfile, running `docker compose build` and executing our test suite again. It's really that simple!

There are of course a few additional things you should do. Be sure to write a little explainer of how devs can get started in your README.md, and add the following lines to your package's .gitattributes file so that the docker files aren't included when people install your package in their application:

```txt
/docker-compose.yml export-ignore
/docker export-ignore
```

Of course, your package might have more complex requirements. For example, the Request Factories package has a reliance on the PHP GD extension and PCOV for code coverage. You can see the Dockerfile behind that [here](https://github.com/worksome/request-factories/blob/main/docker/Dockerfile). You'll note that there isn't much more to it, and for developers contributing to the package, the experience is exactly the same.

Of course, therein lies the magic. The easier it is for developers to get your project up and running locally, the more likely it is that they'll contribute. So, the next time you're building a package, why not spend 2 minutes and add support for Docker?

Happy coding!

Luke
