<?php
declare(strict_types=1);

/*
 * Opus RefBook example: FSM RefBook domain marker.
 *
 * Purpose:
 *   Show how FSM classes describe their domain for documentation extraction.
 */

/*
 * OPUS_REFBOOK:
 *   domain: FSM
 *   role: Class StateMachine belongs to the FSM framework domain.
 *   contract:
 *     - applies explicit transitions only
 *     - rejects unknown state/signal pairs
 *     - produces typed transition results
 *   examples:
 *     - fsm-definition
 *     - fsm-basic-transition
 *     - fsm-action
 *     - fsm-error
 *   diagrams:
 *     - fsm-runtime
 * END_OPUS_REFBOOK
 */
