<?php
declare(strict_types = 1);
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\Data\Exception;

/**
 * Thrown when trying to retrieve a non-existent entry in a collection.
 */
class EntryNotFoundException extends \RuntimeException
{
}
