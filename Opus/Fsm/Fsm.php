<?php
declare(strict_types=1);

namespace Opus\FSM;

final class Fsm
{
    /** @return list<array<string,string>> */
    public function demoFlow(string $lang): array
    {
        return [
            ['state' => 'BOOT', 'signal' => 'HTTP_REQUEST', 'action' => 'Resolve package', 'next' => 'PACKAGE_READY'],
            ['state' => 'PACKAGE_READY', 'signal' => 'LANG_SELECTED', 'action' => 'Load local I18N', 'next' => 'I18N_READY'],
            ['state' => 'I18N_READY', 'signal' => 'ROUTE_MATCH', 'action' => 'Select controller/view', 'next' => 'RENDER_READY'],
            ['state' => 'RENDER_READY', 'signal' => 'SEND_RESPONSE', 'action' => strtoupper($lang) . ' HTML/JSON response', 'next' => 'DONE'],
        ];
    }
}
