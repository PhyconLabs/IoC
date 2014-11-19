<?php
namespace SDS\IoC\Exceptions;

/**
 * Thrown when class argument can't be resolved. Usually happens when scalar value needs to be auto-resolved.
 */
class ArgumentBindingException extends BindingResolveException
{
    // empty
}