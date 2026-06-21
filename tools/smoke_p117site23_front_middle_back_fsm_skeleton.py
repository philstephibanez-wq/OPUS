from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def require(path: str, needle: str) -> None:
    target = ROOT / path
    if not target.exists():
        raise SystemExit(f'MISSING_FILE: {path}')
    text = target.read_text(encoding='utf-8')
    if needle not in text:
        raise SystemExit(f'MISSING_TEXT: {path} {needle}')


require('framework/Opus/FRONT/FrontLayer.php', 'OPUS_FRONT_LAYER_V1')
print('CHECK_FRAMEWORK_FRONT_LAYER=OK')
require('framework/Opus/MIDDLE/MiddleLayer.php', 'OPUS_MIDDLE_LAYER_V1')
print('CHECK_FRAMEWORK_MIDDLE_LAYER=OK')
require('framework/Opus/MIDDLE/FSM/FsmProcessorInterface.php', 'FsmProcessorInterface')
print('CHECK_FRAMEWORK_FSM_PROCESSOR=OK')
require('framework/Opus/BACK/BackLayer.php', 'OPUS_BACK_LAYER_V1')
print('CHECK_FRAMEWORK_BACK_LAYER=OK')
require('DOC/P117SITE23_FRONT_MIDDLE_BACK_FSM_SKELETON.md', 'Every operation path')
print('CHECK_CONTRACT_DOC=OK')
print('P117SITE23_FRONT_MIDDLE_BACK_FSM_SKELETON_SMOKE_OK')
