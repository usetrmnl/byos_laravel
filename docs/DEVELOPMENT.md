## Local Development

#### Requirements

* PHP >= 8.2
* ext-imagick
* puppeteer [see Browsershot docs](https://spatie.be/docs/browsershot/v4/requirements)

#### Clone the repository

```bash
git clone git@github.com:usetrmnl/byos_laravel.git
```

#### Copy environment file

```bash
cp .env.example .env
php artisan key:generate
```

#### Install dependencies and Build frontend

```bash
composer install
npm i
npm run build
```

#### Run migrations

```bash
php artisan migrate --seed
```

#### Link storage to expose public assets

```bash
php artisan storage:link
```

#### Run the server

To expose the built-in server to the local network, you can run the following command:

```bash
php artisan serve  --host=0.0.0.0 --port 4567
```

---

## Docker
Use the provided Dockerfile, or docker-compose file to run the server in a container.

#### .devcontainer

Open this repository in Visual Studio Code with the Dev Containers extension installed. This will automatically build the devcontainer and start the server.

Copy the .env.example.local to .env:

```bash
cp .env.example.local .env
```

Run migrations and seed the database:

```bash
php artisan migrate --seed
```

Link storage to expose public assets:

```bash
php artisan storage:link
```

Server is ready. Visit tab "Ports" in VSCode and visit the "Forwarded Address" in your browser.

Login with user / password `admin@example.com` / `admin@example.com`

##### After Pull: Install Packages and Build Frontend

```bash
composer install
npm i
npm run build
```
