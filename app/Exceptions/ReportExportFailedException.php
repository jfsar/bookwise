<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A report export failed in a way that will never succeed on retry — for
 * example the selected report and filters return no rows. Queue reliability
 * (§0.25/§0.30) requires such permanent failures to fail fast instead of
 * consuming the retry budget, unlike transient storage or network errors.
 */
class ReportExportFailedException extends RuntimeException {}
