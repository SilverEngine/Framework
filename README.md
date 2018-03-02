

<p align="center"><img src="https://thumb.ibb.co/fDOcRG/goodone.jpg"></p>


## SILVER ENGINE FRAMEWORK

SilverEngine is a powerful PHP Dynamical MVC framework built for developers who need a simple and elegant toolkit to create powerfull full-featured web applications.

![Licence](https://img.shields.io/badge/Licence-MIT-green.svg)
![PHP5.6](https://img.shields.io/badge/php-5.6-blue.svg)
![version Alpha](https://img.shields.io/badge/Alpha-V1.0.4-green.svg)


## Check Demo portfolio

The [Demo page](https://silverengine.net/demo/)
Download zip demo file [Download here](https://github.com/SilverEngine/Framework/archive/portfolio.zip)


## Documentation

The [Documentation](https://silverengine.net/docs.html) of the framework is still work in progress (WIP).
The WIKI [WIKI Documentation](https://github.com/SilverEngine/Framework/wiki) of the framework is still work in progress (WIP).

## install

> Webpage [silverengine.net](https://silverengine.net)

> Documentation [Documentation](https://silverengine.net/docs)

## Download and setup

> 1. Download master version 1.0.3 [Download now](https://github.com/SilverEngine/Framework/releases/tag/1.0.3)
> 2. Setup host configuration for webserver  apache2 or nginix and point to  public/ directory
> 3. Run composer update

## Tutorial how to create a blog
> We prepare simple tutorial how to work with SilverEngine  [How to create a blog](https://github.com/SilverEngine/Framework/wiki/Create-a-blog)

## Tutorial how to use ReflectORM  (SilverEngine ORM)
> We prepare simple tutorial how to work with SilverEngine  [ReflectORM](https://github.com/SilverEngine/Framework/wiki/ReflectORM)

## Tutorial how to use Ghost TE (Ghost template engine)
> Our brand new Template engine - tutorials still WIP  [How to work with Ghost template engine](https://github.com/SilverEngine/Framework/wiki/Working-with-ghost)



## Contributing

Thank you for considering contributing to the framework!

Special thanks

- [lotfio-lakehal](https://github.com/lotfio-lakehal)
- [nmarulo](https://github.com/nmarulo)
- [antigov](https://github.com/antigov)

### Rules to follow

1. Same tree structure
2. PSR-4 and PSR-2 
3. Namespace need to start with an Alias \Silver\
4. Follow manual for pagkagist  [Packagist docs](https://packagist.org/)
5. (Optional) join us on Discord server [Join here](https://discord.gg/cwMygSP)
5. Make docs with .MD
6. Code need to be unit tested - php v5.6 |  [PHPUnit](https://phpunit.de/index.html)


### Tree
> For easy use please Src/ file direct the namespace to alias 
```php 
\Silver\<name of your project>\<classes>
```

```php
<project root>
├─ config/
├─ docs/
├─ node_modules/
├─ public/
......└── index.php
├─ src/
......├── Controllers/
............└── DefaultController.php
......├── Facades/
......├── Helpers/
......├── Templates/
......└── Services/
├─ tests/
├─ translations/
├─ var/
......├─ cache/
......├─ log/
......└─ session/
├─ vendor/
└─ composer.json
```
## Security & Vulnerabilities

If you discover a security vulnerability within our engine, please send us email at support@silverengine.net

## License

The Silver Engine framework is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
