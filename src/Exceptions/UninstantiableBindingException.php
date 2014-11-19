<?php
namespace SDS\IoC\Exceptions;

/**
 * Thrown when class binding can't be instantiated ( typically when trying to auto-resolver tries to resolve
 * abstract classes or interfaces ).
 */
class UninstantiableBindingException extends BindingResolveException
{
    // empty
}