from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def require(path, needle):
    target = ROOT / path
    if not target.exists():
        raise SystemExit(f'MISSING_FILE: {path}')
    text = target.read_text(encoding='utf-8')
    if needle not in text:
        raise SystemExit(f'MISSING_TEXT: {path} {needle}')


require('framework/Opus/FRONT/FrontLayer.php', 'OPUS_FRONT_LAYER_V1')
print('CHECK_FRONT_BOUNDARY=OK')
require('framework/Opus/MIDDLE/MiddleLayer.php', 'OPUS_MIDDLE_LAYER_V1')
print('CHECK_MIDDLE_BOUNDARY=OK')
require('framework/Opus/MIDDLE/FSM/FsmProcessorInterface.php', 'FsmProcessorInterface')
require('framework/Opus/MIDDLE/FSM/FsmSignal.php', 'FsmSignal')
require('framework/Opus/MIDDLE/FSM/FsmResult.php', 'FsmResult')
require('framework/Opus/MIDDLE/FSM/FsmTransition.php', 'FsmTransition')
require('framework/Opus/MIDDLE/FSM/fsm.transitions.json', 'OPUS_MIDDLE_FSM_TRANSITIONS_V1')
print('CHECK_MIDDLE_FSM_TRANSITIONS=OK')
require('framework/Opus/BACK/BackLayer.php', 'OPUS_BACK_LAYER_V1')
print('CHECK_BACK_BOUNDARY=OK')
require('framework/Opus/COMMON/CommonLayer.php', 'OPUS_COMMON_LAYER_V1')
require('framework/Opus/COMMON/README.md', 'not a shared junk drawer')
print('CHECK_COMMON_NOT_CATCH_ALL=OK')
require('framework/Opus/COMMON/Contract/BoundaryContractInterface.php', 'BoundaryContractInterface')
require('framework/Opus/COMMON/Contract/FsmTransitionContract.php', 'FsmTransitionContract')
require('framework/Opus/COMMON/Dto/RequestEnvelope.php', 'RequestEnvelope')
require('framework/Opus/COMMON/Dto/ResponseEnvelope.php', 'ResponseEnvelope')
require('framework/Opus/COMMON/Result/OperationResult.php', 'OperationResult')
require('framework/Opus/COMMON/Error/TypedError.php', 'TypedError')
require('framework/Opus/COMMON/ValueObject/LayerName.php', 'LayerName')
print('CHECK_COMMON_SHARED_LANGUAGE=OK')
require('DOC/P117SITE24_FRONT_MIDDLE_BACK_COMMON_BOUNDARIES.md', '```mermaid')
require('DOC/P117SITE24_FRONT_MIDDLE_BACK_COMMON_BOUNDARIES.md', 'stateDiagram-v2')
require('DOC/P117SITE24_FRONT_MIDDLE_BACK_COMMON_BOUNDARIES.md', 'sequenceDiagram')
require('DOC/P117SITE24_FRONT_MIDDLE_BACK_COMMON_BOUNDARIES.md', 'COMMON anti catch-all rule')
print('CHECK_MERMAID_DOCUMENTATION=OK')
print('P117SITE24_FRONT_MIDDLE_BACK_COMMON_BOUNDARIES_SMOKE_OK')
