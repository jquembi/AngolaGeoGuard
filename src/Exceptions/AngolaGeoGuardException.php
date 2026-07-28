<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Exceptions;

use RuntimeException;

/**
 * Excecao base de todas as excecoes lancadas pelo pacote.
 * Permite `catch (AngolaGeoGuardException $e)` para capturar qualquer
 * erro proveniente do angola-geoguard.
 */
class AngolaGeoGuardException extends RuntimeException
{
}
