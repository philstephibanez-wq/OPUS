<?php
declare(strict_types=1);

namespace Opus\Rcp\Rest;

use Opus\Http\Request;
use Opus\Http\Response;

interface RcpRestServerInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function handle(Request $request): Response;
}
