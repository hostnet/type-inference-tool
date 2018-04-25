<?php
/**
 * @copyright 2017-2018 Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\TypeInference\Analyzer\Data\Exception;

/**
 * Thrown when trying to retrieve a non-existent entry in a collection.
 */
class EntryNotFoundException extends \RuntimeException
{
}
