<?php
/**
 * @author ueaner <ueaner@gmail.com>
 */

if (!interface_exists('Throwable') && !class_exists('Throwable')) {
    class Throwable extends Exception
    {
    }
}
