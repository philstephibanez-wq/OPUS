<?php

declare(strict_types=1);

namespace Opus\Security;

use ASAP\Acl\AccessRule;
use ASAP\Acl\PrivilegeDefinition;
use ASAP\Acl\ResourceDefinition;
use ASAP\Acl\RoleDefinition;
use ASAP\Contract\ContractException;
use ASAP\Fsm\StateDefinition;
use ASAP\Fsm\TransitionDefinition;
use SimpleXMLElement;

/*
 * OPUS_REFBOOK:
 *   domain: SECURITY
 *   role: Class SiteSecurityPolicyLoader belongs to the SECURITY Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the SECURITY domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - security-overview
 *   diagrams:
 *     - security-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LOADER
 *
 * Role:
 *   Load one explicit Opus site security policy from XML.
 *
 * Responsibility:
 *   Convert site security XML into typed FSM and ACL declarations.
 *
 * Contract:
 *   No implicit policy. Missing or malformed nodes fail explicitly.
 *
 * Since:
 *   P112D2
 */
final class SiteSecurityPolicyLoader
{
    /**
     * PUBLIC API
     *
     * @param string $securityFile Site security XML path.
     *
     * @return SiteSecurityPolicy Typed site policy.
     */
    public function load(string $securityFile): SiteSecurityPolicy
    {
        if (!is_file($securityFile)) {
            throw ContractException::because('OPUS_SITE_SECURITY_FILE_MISSING', $securityFile);
        }

        $xml = simplexml_load_file($securityFile);

        if (!$xml instanceof SimpleXMLElement) {
            throw ContractException::because('OPUS_SITE_SECURITY_XML_INVALID', $securityFile);
        }

        if (!isset($xml->fsm)) {
            throw ContractException::because('OPUS_SITE_SECURITY_FSM_MISSING', $securityFile);
        }

        if (!isset($xml->acl)) {
            throw ContractException::because('OPUS_SITE_SECURITY_ACL_MISSING', $securityFile);
        }

        $fsm = $xml->fsm;
        $acl = $xml->acl;

        $states = [];
        foreach ($fsm->states->state ?? [] as $stateNode) {
            $states[] = new StateDefinition((string) $stateNode['id'], (string) ($stateNode['label'] ?? ''));
        }

        $transitions = [];
        foreach ($fsm->transitions->transition ?? [] as $transitionNode) {
            $transitions[] = new TransitionDefinition(
                (string) $transitionNode['from'],
                (string) $transitionNode['signal'],
                (string) $transitionNode['to']
            );
        }

        $roles = [];
        foreach ($acl->roles->role ?? [] as $roleNode) {
            $roles[] = new RoleDefinition((string) $roleNode['id']);
        }

        $resources = [];
        foreach ($acl->resources->resource ?? [] as $resourceNode) {
            $resources[] = new ResourceDefinition((string) $resourceNode['id']);
        }

        $privileges = [];
        foreach ($acl->privileges->privilege ?? [] as $privilegeNode) {
            $privileges[] = new PrivilegeDefinition((string) $privilegeNode['id']);
        }

        $rules = [];
        foreach ($acl->rules->allow ?? [] as $allowNode) {
            $rules[] = new AccessRule(
                (string) $allowNode['role'],
                (string) $allowNode['resource'],
                (string) $allowNode['privilege'],
                true
            );
        }

        foreach ($acl->rules->deny ?? [] as $denyNode) {
            $rules[] = new AccessRule(
                (string) $denyNode['role'],
                (string) $denyNode['resource'],
                (string) $denyNode['privilege'],
                false
            );
        }

        return new SiteSecurityPolicy(
            (string) $fsm['initialState'],
            (string) $fsm['requestSignal'],
            $states,
            $transitions,
            (string) $acl['role'],
            (string) $acl['resource'],
            (string) $acl['privilege'],
            $roles,
            $resources,
            $privileges,
            $rules
        );
    }
}
