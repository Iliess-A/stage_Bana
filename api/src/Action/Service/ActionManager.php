<?php

/**
 * Copyright (c) 2020, MOBICOOP. All rights reserved.
 * This project is dual licensed under AGPL and proprietary licence.
 ***************************
 *    This program is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU Affero General Public License as
 *    published by the Free Software Foundation, either version 3 of the
 *    License, or (at your option) any later version.
 *
 *    This program is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU Affero General Public License for more details.
 *
 *    You should have received a copy of the GNU Affero General Public License
 *    along with this program.  If not, see <gnu.org/licenses>.
 ***************************
 *    Licence MOBICOOP described in the file
 *    LICENSE
 **************************/

namespace App\Action\Service;

use App\Action\Entity\Action;
use App\Action\Entity\Animation;
use App\Action\Repository\ActionRepository;
use App\Action\Service\DiaryManager;
use App\App\Entity\App;
use App\Communication\Service\NotificationManager;
use App\Solidary\Entity\Solidary;
use App\Solidary\Event\SolidaryCreated;
use App\Solidary\Event\SolidaryUpdated;
use App\Solidary\Event\SolidaryUserCreated;
use App\Solidary\Event\SolidaryUserStructureAccepted;
use App\Solidary\Event\SolidaryUserStructureRefused;
use App\Solidary\Event\SolidaryUserUpdated;
use App\Solidary\Exception\SolidaryException;
use App\User\Entity\User;
use Symfony\Component\Security\Core\Security;

/**
 * Action Manager
 *
 * @author Maxime Bardot <maxime.bardot@mobicoop.org>
 */
class ActionManager
{
    private $notificationManager;
    private $diaryManager;
    private $actionRepository;
    private $security;

    public function __construct(NotificationManager $notificationManager, DiaryManager $diaryManager, ActionRepository $actionRepository, Security $security)
    {
        $this->notificationManager = $notificationManager;
        $this->diaryManager = $diaryManager;
        $this->actionRepository = $actionRepository;
        $this->security = $security;
    }
    
    /**
     * Handle a Solidary Event
     *
     * @param string $actionName    Name of the action of this event
     * @param Object $object        Event of the action
     * @return void
     */
    public function handleAction(string $actionName, Object $object=null)
    {
        // Get the action
        $action = $this->actionRepository->findOneBy(['name'=>$actionName]);
        if (empty($action)) {
            throw new SolidaryException(SolidaryException::BAD_SOLIDARY_ACTION);
        }
        switch ($actionName) {
            case SolidaryUserStructureAccepted::NAME:$this->onSolidaryUserStructureAccepted($action, $object);
                break;
            case SolidaryUserStructureRefused::NAME:$this->onSolidaryUserStructureRefused($action, $object);
                break;
            case SolidaryUserCreated::NAME:$this->onSolidaryUserCreated($action, $object);
                break;
            case SolidaryUserUpdated::NAME:$this->onSolidaryUserUpdated($action, $object);
                break;
            case SolidaryCreated::NAME:$this->onSolidaryCreated($action, $object);
                break;
            case SolidaryUpdated::NAME:$this->onSolidaryUpdated($action, $object);
                break;
            default: $this->treatAction($action, $object);
                break;
        }
    }

    private function treatAction(Action $action, Animation $animation)
    {
        if ($action->isInLog()) {
            // To do
        }
        if ($action->isInDiary()) {
            $this->diaryManager->addDiaryEntry(
                $action,
                $animation->getUser(),
                $animation->getAuthor(),
                $animation->getComment(),
                $animation->getSolidary(),
                $animation->getSolidarySolution(),
                $animation->getProgression()
            );
        }
    }

    private function onSolidaryUserStructureAccepted(Action $action, SolidaryUserStructureAccepted $event)
    {
        $user = $event->getSolidaryUserStructure()->getSolidaryUser()->getUser();
        $admin = $this->security->getUser();
        $this->diaryManager->addDiaryEntry($action, $user, $admin);
    }

    private function onSolidaryUserStructureRefused(Action $action, SolidaryUserStructureRefused $event)
    {
        $user = $event->getSolidaryUserStructure()->getSolidaryUser()->getUser();
        $admin = $this->security->getUser();
        $this->diaryManager->addDiaryEntry($action, $user, $admin);
    }

    private function onSolidaryUserCreated(Action $action, SolidaryUserCreated $event)
    {
        $this->diaryManager->addDiaryEntry($action, $event->getUser(), $event->getAuthor());
    }

    private function onSolidaryUserUpdated(Action $action, SolidaryUserUpdated $event)
    {
        $user = $event->getSolidaryUser()->getUser();
        $admin = $this->security->getUser();
        $this->diaryManager->addDiaryEntry($action, $user, $admin);
    }

    private function onSolidaryCreated(Action $action, SolidaryCreated $event)
    {
        // To do : The solidary is not persisted yet so we can't pass it to addDiaryEntrey... But it would be cool :)
        $this->diaryManager->addDiaryEntry($action, $event->getUser(), $event->getAuthor());
    }

    private function onSolidaryUpdated(Action $action, SolidaryUpdated $event)
    {
        $user = $event->getSolidary()->getSolidaryUserStructure()->getSolidaryUser()->getUser();
        $admin = $this->security->getUser();
        $this->diaryManager->addDiaryEntry($action, $user, $admin, null, $event->getSolidary());
    }
}