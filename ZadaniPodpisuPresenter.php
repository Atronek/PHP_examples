<?php

namespace App\RegistryModule\Presenters;

use App\Model\Entities\UzivatelPodpis;
use App\Model\Repositories\UzivatelPodpisRepository;
use App\Presenters\BaseSecuredPresenter;
use Nette\Application\UI\Form;
use App\Components\PodpisovePoleControl;
use App\Lib\BootstrapV4Renderer;
use App\Model\Repositories\UzivateleRepository;

class ZadaniPodpisuPresenter extends BaseSecuredPresenter
{

    /**
     * @var UzivatelPodpisRepository
     * @inject
     */
    public $uzivatelPodpisRepository;

    /**
     * @var UzivateleRepository
     * @inject
     */
    public $uzivateleRepository;

    private $userId;

    public function actionDefault($idUzivatel)
    {
        $this->userId = $idUzivatel;
    }

    public function createComponentPodpisForm()
    {
        $form = new form();

        $form->setRenderer(new BootstrapV4Renderer);

        $podpis = new PodpisovePoleControl();
        $form->addComponent($podpis, "podpis");

        $form->addSubmit("ulozit", "Uložit")
            ->setAttribute("class", "btn btn-primary");

        $zaznam = $this->uzivatelPodpisRepository->findOneBy(["uzivatel" => $this->userId]);

        if (!($zaznam == null)) {
            try {
                $cesta = "./storage/expedicePodpisy/interni/" . $this->userId . '.txt';
                $string = $this->uzivatelPodpisRepository->nacistPodpisZeSouboru($cesta);

                $form->setDefaults(["podpis" => $string]);
            } catch (\Throwable $th) {
                $this->flashMessage("Chyba při vytvoření podpisu!", "alert alert-warning");
                $this->redirect("PrehledPodpisu:");
            }
        }

        $form->onSuccess[] = [$this, 'ukladaniPodpisu'];

        return $form;
    }

    public function ukladaniPodpisu($form, $values)
    {
        try {
            $cesta = "./storage/expedicePodpisy/interni/" . $this->userId . '.txt';
            $this->uzivatelPodpisRepository->ulozitPodpisDoSouboru($cesta, $values["podpis"]);
        } catch (\Throwable $th) {
            $this->flashMessage("Chyba při vytvoření podpisu!", "alert alert-warning");
            $this->redirect("PrehledPodpisu:");
        }

        $zaznam = $this->uzivatelPodpisRepository->findOneBy(["uzivatel" => $this->userId]);

        if ($zaznam == null) {
            $uzivatelPodpis = new UzivatelPodpis;
            $uzivatelPodpis->setUzivatel($this->userId);
        } else {
            $uzivatelPodpis = $zaznam;
        }

        $uzivatelPodpis->setPodpisPath($cesta);

        $this->uzivatelPodpisRepository->em->persist($uzivatelPodpis);
        $this->uzivatelPodpisRepository->em->flush();

        $this->redirect("PrehledPodpisu:");
    }
}
