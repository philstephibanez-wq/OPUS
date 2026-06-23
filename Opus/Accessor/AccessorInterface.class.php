<?php

/**
 * OPUS accessor contract.
 *
 * Implementations must expose controlled access to protected/internal
 * properties through explicit get()/set()/has() calls. Dynamic getXxx(),
 * setXxx() and hasXxx() are implemented by OPUS_Singleton through __call().
 */
interface OPUS_AccessorInterface {
    public function get($property);
    public function set($property, $value);
    public function has($property): bool;
}

?>
