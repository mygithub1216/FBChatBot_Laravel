<?php namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Transformers\BaseTransformer;
use App\Services\MessagePreviewService;
use App\Services\Validation\MessageValidationHelper;

class MessagePreviewController extends APIController
{

    use MessageValidationHelper;

    /**
     * @type MessagePreviewService
     */
    private $messagePreviews;

    /**
     * WelcomeMessageController constructor.
     * @param MessagePreviewService $messagePreviews
     */
    public function __construct(MessagePreviewService $messagePreviews)
    {
        $this->messagePreviews = $messagePreviews;
    }
    
    /**
     * Create a message preview.
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function store(Request $request)
    {
        $rules = $this->validationRules();
        $this->validate($request, $rules);

        $this->messagePreviews->createAndSend($request->all(), $this->user(), $this->bot());

        return $this->response->created();
    }
    
    /** @return BaseTransformer */
    protected function transformer()
    {
    }

    private function validationRules()
    {
        return [
            'template'            => 'bail|required|array',
            'template.messages'   => 'bail|required|array|max:10',
            'template.messages.*' => 'bail|required|message',
        ];
    }
}
