<?php namespace App\Transformers;

use App\Models\MainMenu;

class MainMenuTransformer extends BaseTransformer
{

    public $defaultIncludes = ['buttons'];

    public function transform(MainMenu $mainMenu)
    {
        return [];
    }

    public function includeButtons(MainMenu $mainMenu)
    {
        return $this->collection($mainMenu->buttons, new MessageTransformer(), false);
    }
}