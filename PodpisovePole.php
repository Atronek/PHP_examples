<?php

namespace App\Components;

use Nette\Forms\Controls\BaseControl;
use Nette\Utils\Html;

/**
 * Potřebuje fabric.min.js pro svou funkci
 */
class PodpisovePoleControl extends BaseControl
{

    const INTERNI = "interni";
    const RIDICI = "ridici";

    private $x;
    private $y;
    private $isReadOnly;

    public function __construct($label = null, $isReadOnly = false, $x = 400, $y = 200)
    {
        parent::__construct($label);
        $this->x = $x;
        $this->y = $y;
        $this->isReadOnly = $isReadOnly;
    }

    public function getControl()
    {
        $control = parent::getControl();
        $name = $this->getHtmlName();

        $control->addAttributes(["id" => $name . "-canvas-output", "class" => "pathDiv d-none", "value" => $this->value]);

        $mainDiv = Html::el("div", [
            "class" => "podpisove-pole"
        ]);

        $mainDiv->addHtml($control);

        $mainDiv->create("canvas", [
            "class" => "myCan",
            "id" => $name . "-canvas-id",
            "width" => $this->x,
            "height" => $this->y,
            $this->isReadOnly ? "readonly" : "" => ""
        ]);

        // Možnost linkout js přímo tady akorát by se načítal vícekrát takže by bylo třeba ošetřit
        // $mainDiv->create("script", [
        //     "src" => "docela/lol/poganek.js"
        // ]);

        return $mainDiv;
    }

    public function loadHttpData()
    {
        $this->value = $this->getHttpData(\Nette\Forms\Form::DATA_LINE);
    }

    public function getValue()
    {
        return $this->value;
    }
}
