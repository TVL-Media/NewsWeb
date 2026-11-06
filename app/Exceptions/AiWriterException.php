<?php

namespace App\Exceptions;

use RuntimeException;

/** The Claude API call failed, was refused, or returned unusable output. */
class AiWriterException extends RuntimeException {}
