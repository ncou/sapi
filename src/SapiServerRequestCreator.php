<?php

declare(strict_types=1);

namespace Chiron\Sapi;

use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;
use Chiron\Container\Container;

final class SapiServerRequestCreator
{
    // TODO : stocker dans une variable static de classe l'instance de $creator, cela évitera de réinstancier cette classe via la méthode make X fois, on gagnera en utilisation mémoire.
    public static function fromGlobals(): ServerRequestInterface
    {
        // TODO : nettoyer le code, eventuellement faire une fonction globale make() qui crée automatiquement les classes dont le nom est passée en paramétre.
        // TODO : eventuellement faire en sorte que si on appel juste container() sans paramétre cela retourne l'instance courrante du container !!!
        $container = Container::$instance;
        $creator = $container->make(ServerRequestCreator::class); // TODO : il doit exister une méthode resolve() qui est globale et doit utiliser le container pour effectuer un make. il faudrait utiliser cette fonction pour rendre le code plus propre !!!

        return $creator->fromGlobals();
    }
}
