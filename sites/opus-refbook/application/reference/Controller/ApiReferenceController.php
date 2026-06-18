<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Controller;

use Opus\Http\Request;
use Opus\Renderer\ViewModel;

final class ApiReferenceController extends AbstractRefBookController
{
    /**
     * @param array<string,string> $params
     */
    public function index(Request $request, array $params): ViewModel
    {
        return $this->view('pages/api-reference.score', [
            'title' => $this->content()->t('api.title'),
            'overview' => $this->catalog()->overview(),
        ]);
    }
}
