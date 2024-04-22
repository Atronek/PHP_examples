<?php

namespace App\ExpediceModule\Presenters;

use App\Components\PodpisovePoleControl;
use App\Lib\BootstrapV4Renderer;
use App\Model\Entities\VydejeTelefonu;
use App\Model\Repositories\ExpediceTelefonRepository;
use App\Model\Repositories\UzivateleRepository;
use App\Model\Repositories\UzivatelPodpisRepository;
use App\Model\Repositories\VydejeTelefonuRepository;
use App\Presenters\BaseSecuredPresenter;
use DateTime;
use Nette\Application\UI\Form;
use PhpOffice\PhpSpreadsheet\Shared\Date;

final class PredavaciProtokolPresenter extends BaseSecuredPresenter
{

    /**
     * @var ExpediceTelefonRepository
     * @inject
     */
    public $expediceTelefonRepository;

    /**
     * @var VydejeTelefonuRepository
     * @inject
     */
    public $vydejeTelefonuRepository;

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

    private $idEntity;
    private $userId;
    private $userPodpisPath;

    public function actionDefault($id = null, $podpisDefault = null, $zamestId = null)
    {
        $this->idEntity = $id;
        if ($zamestId == null) {
            $this->userId = $this->user->getId();
        } else {
            $this->userId = $zamestId;
        }
        
        if ($podpisDefault == null) {
            $this->userPodpisPath = "./storage/expedicePodpisy/interni/" . $this->userId . '.txt';
        } else {
            $this->userPodpisPath = $podpisDefault;
        }

        $this->template->showDalsiPodpisy = false;
        if ($id) {
            $this->template->showDalsiPodpisy = true;
        }

        $this->template->status = false;

    }

    public function createComponentPredavaciForm()
    {
        $form = new form();

        $readOnly = !($this->idEntity == null);

        if ($this->idEntity) {
            $vydejeTelefonu = $this->vydejeTelefonuRepository->find($this->idEntity);
        }

        $form->setRenderer(new BootstrapV4Renderer);

        $form->addText('name', 'Jméno a příjmení')
            ->setRequired()
            ->setDisabled($readOnly)
            ->setAttribute("class", "form-control");

        $form->addText('spz', 'SPZ-AUTA')
            ->setRequired()
            ->setDisabled($readOnly)
            ->setAttribute("class", "form-control");

        $telefony = $this->expediceTelefonRepository->findDataProSelect();
        $form->addSelect("telefon", "Telefon", $telefony)
            ->setRequired()
            ->setPrompt('Vyberte telefon!')
            ->setDisabled($readOnly)
            ->setAttribute("class", "form-control");

        $podpisZamestnanecPredal = new PodpisovePoleControl(null, true);
        $form->addComponent($podpisZamestnanecPredal, "podpisZamestnanecPredal");

        $podpisRidicPrevzal = new PodpisovePoleControl(null, !($this->idEntity == null));
        $form->addComponent($podpisRidicPrevzal, "podpisRidicPrevzal");

        if ($this->idEntity) {
            $podpisRidicPredal = new PodpisovePoleControl(null, !($vydejeTelefonu->getRidicPodpisPredal() == ""));
            $form->addComponent($podpisRidicPredal, "podpisRidicPredal");

            $podpisZamestnanecPrevzal = new PodpisovePoleControl(null, true);
            $form->addComponent($podpisZamestnanecPrevzal, "podpisZamestnanecPrevzal");
        }

        if ($this->idEntity == null) {

            $form->addSubmit("ulozit", "Uložit")
                ->setAttribute("class", "btn btn-primary")
                ->setAttribute("style", "width:100%");

            $form->onSuccess[] = [$this, 'ukladaniPodpisu'];
        } elseif ($vydejeTelefonu->getRidicPodpisPredal() != null) {
            $form->addSubmit("ulozit", "Zpět")
                ->setAttribute("class", "btn btn-primary")
                ->setAttribute("style", "width:100%");

            $form->onSuccess[] = [$this, 'zpetNaProtokoly'];
        } else {
            $form->addSubmit("ulozit", "Uložit")
                ->setAttribute("class", "btn btn-primary")
                ->setAttribute("style", "width:100%");

            $form->onSuccess[] = [$this, 'ukladaniPodpisu'];
        }





        $defaults = [];
        //Načtení default hodnot
        if ($this->idEntity) {

            //najit default hodnoty k zaznamu
            $zaznam = $this->vydejeTelefonuRepository->find($this->idEntity);
            $defaults = ["name" => $zaznam->getRidicJmeno(), "spz" => $zaznam->getSpz(), "telefon" => $zaznam->getTelefon()->getId()];

            //default hodnota k podpisu ridice prevzal
            $cestaRidicPrevzal = $vydejeTelefonu->getRidicPodpisPrevzal();
            $stringRidicPrevzal = $this->uzivatelPodpisRepository->nacistPodpisZeSouboru($cestaRidicPrevzal);
            $defaults["podpisRidicPrevzal"] = $stringRidicPrevzal;

            $uzivatelPredal = $this->uzivatelPodpisRepository->findOneBy(["uzivatel" => $zaznam->getZamestnanecPodpisPredal()])->getPodpisPath();
            $stringUzivatelPredal = $this->uzivatelPodpisRepository->nacistPodpisZeSouboru($uzivatelPredal);
            $defaults["podpisZamestnanecPredal"] = $stringUzivatelPredal;


            $stringUzivatelPrevzal = $this->uzivatelPodpisRepository->nacistPodpisZeSouboru($this->userPodpisPath);
            $defaults["podpisZamestnanecPrevzal"] = $stringUzivatelPrevzal;

            //default hodnota k podpisu ridice predal
            $cestaRidicPredal = $vydejeTelefonu->getRidicPodpisPredal();
            if (!($cestaRidicPredal == null)) {
                $stringRidicPredal = $this->uzivatelPodpisRepository->nacistPodpisZeSouboru($cestaRidicPredal);
                $defaults["podpisRidicPredal"] = $stringRidicPredal;

                $uzivatelPrevzal = $this->uzivatelPodpisRepository->findOneBy(["uzivatel" => $zaznam->getZamestnanecPodpisPrevzal()])->getPodpisPath();
                $stringUzivatelPrevzal = $this->uzivatelPodpisRepository->nacistPodpisZeSouboru($uzivatelPrevzal);
                $defaults["podpisZamestnanecPrevzal"] = $stringUzivatelPrevzal;
    
            }

        } else {
            //default hodnota k podpisu uživatele
            $stringUzivatelPredal = $this->uzivatelPodpisRepository->nacistPodpisZeSouboru($this->userPodpisPath);
            $defaults["podpisZamestnanecPredal"] = $stringUzivatelPredal;
        }

        $form->setDefaults($defaults);

        return $form;
    }



    public function ukladaniPodpisu($form, $values)
    {
        if ($this->idEntity == null) {
            try {
                $cesta = "./storage/expedicePodpisy/ridici/" . uniqid("prevzal-") . '.txt';
                $this->uzivatelPodpisRepository->ulozitPodpisDoSouboru($cesta, $values["podpisRidicPrevzal"]);
            } catch (\Throwable $th) {
                $this->flashMessage("Chyba při vytvoření podpisu!", "alert alert-warning");
                $this->redirect("PrehledVydejeTelefonu:");
            }
        }

        if ($this->idEntity) {

            /** @var VydejeTelefonu */
            $vydejeTelefonu = $this->vydejeTelefonuRepository->find($this->idEntity);

            try {
                $cesta = "./storage/expedicePodpisy/ridici/" . uniqid("predal-") . '.txt';
                $this->uzivatelPodpisRepository->ulozitPodpisDoSouboru($cesta, $values["podpisRidicPredal"]);
            } catch (\Throwable $th) {
                $this->flashMessage("Chyba při vytvoření podpisu!", "alert alert-warning");
                $this->redirect("PrehledVydejeTelefonu:");
            }

            $vydejeTelefonu->setDatumVraceni(new DateTime());
            $vydejeTelefonu->setRidicPodpisPredal($cesta);
            $vydejeTelefonu->setZamestnanecPodpisPrevzal($this->userId);

            $this->vydejeTelefonuRepository->em->persist($vydejeTelefonu);
            $this->vydejeTelefonuRepository->em->flush();
            $this->redirect('PrehledVydejeTelefonu:');
        } else {

            $telefon = $this->expediceTelefonRepository->find($values['telefon']);
            $zamestnanec = $this->uzivateleRepository->find($this->user->getId());

            /** @var VydejeTelefonu */
            $vydejeTelefonu = new VydejeTelefonu;

            $vydejeTelefonu->setRidicJmeno($values['name']);
            $vydejeTelefonu->setSpz($values['spz']);
            $vydejeTelefonu->setTelefon($telefon);
            $vydejeTelefonu->setRidicPodpisPrevzal($cesta);
            $vydejeTelefonu->setZamestnanecPodpisPredal($this->userId);
            $vydejeTelefonu->setDatumPredani(new DateTime());
            $vydejeTelefonu->setZamestnanec($zamestnanec); // very gut :)


            $this->vydejeTelefonuRepository->em->persist($vydejeTelefonu);
            $this->vydejeTelefonuRepository->em->flush();
            $this->redirect('PrehledVydejeTelefonu:');
        }
    }

    public function zpetNaProtokoly()
    {
        $this->redirect('PrehledVydejeTelefonu:');
    }

    public function startup()
    {
        parent::startup();
        if (!$this->user->isInRole("pps_expedice_protokol")) {
            $this->flashMessage($this->translator->translate('messages.nenipristup'), 'alert alert-warning');
            $this->redirect(':Public:Homepage:default');
        }
    }
}
