<?php

/**
 * Copyright (c) 2019, MOBICOOP. All rights reserved.
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

namespace App\Carpool\Service;

use App\Carpool\Entity\Ad;
use App\Carpool\Entity\Criteria;
use App\Carpool\Entity\Proposal;
use App\Carpool\Entity\Waypoint;
use App\Community\Exception\CommunityNotFoundException;
use App\Community\Service\CommunityManager;
use App\Event\Exception\EventNotFoundException;
use App\Event\Service\EventManager;
use App\Geography\Entity\Address;
use App\Carpool\Exception\AdException;
use App\User\Exception\UserNotFoundException;
use App\User\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Ad manager service.
 *
 * @author Sylvain Briat <sylvain.briat@covivo.eu>
 */
class AdManager
{
    private $entityManager;
    private $proposalManager;
    private $userManager;
    private $communityManager;
    private $eventManager;
    private $resultManager;
    private $params;
    private $logger;

    /**
     * Constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param ProposalManager $proposalManager
     */
    public function __construct(EntityManagerInterface $entityManager, ProposalManager $proposalManager, UserManager $userManager, CommunityManager $communityManager, EventManager $eventManager, ResultManager $resultManager, LoggerInterface $logger, array $params)
    {
        $this->entityManager = $entityManager;
        $this->proposalManager = $proposalManager;
        $this->userManager = $userManager;
        $this->communityManager = $communityManager;
        $this->eventManager = $eventManager;
        $this->resultManager = $resultManager;
        $this->logger = $logger;
        $this->params = $params;
    }
    
    /**
     * Create an ad.
     * This method creates a proposal, and its linked proposal for a return trip.
     * It returns the ad created, with its outward and return results.
     *
     * @param Ad $ad            The ad to create
     * @return Ad
     */
    public function createAd(Ad $ad)
    {
        $this->logger->info('Ad creation | Start ' . (new \DateTime("UTC"))->format("Ymd H:i:s.u"));

        $outwardProposal = new Proposal();
        $outwardCriteria = new Criteria();

        // validation

        // try for an anonymous post ?
        if (!$ad->isSearch() && !$ad->getUserId()) {
            throw new AdException('Anonymous users can\'t post an ad');
        }

        // we set the user of the proposal
        if ($ad->getUserId()) {
            if ($user = $this->userManager->getUser($ad->getUserId())) {
                $outwardProposal->setUser($user);
            } else {
                throw new UserNotFoundException('User ' . $ad->getUserId() . ' not found');
            }
        }
        
        // we check if the ad is posted for another user (delegation)
        if ($ad->getPosterId()) {
            if ($poster = $this->userManager->getUser($ad->getPosterId())) {
                $outwardProposal->setUserDelegate($poster);
            } else {
                throw new UserNotFoundException('Poster ' . $ad->getPosterId() . ' not found');
            }
        }

        // the proposal is private if it's a search only ad
        $outwardProposal->setPrivate($ad->isSearch() ? true : false);

        // we check if it's a round trip
        if ($ad->isOneWay()) {
            // the ad has explicitly been set to one way
            $outwardProposal->setType(Proposal::TYPE_ONE_WAY);
        } elseif (is_null($ad->isOneWay())) {
            // the ad type has not been set, we assume it's a round trip for a regular trip and a one way for a punctual trip
            if ($ad->getFrequency() == Criteria::FREQUENCY_REGULAR) {
                $ad->setOneWay(false);
                $outwardProposal->setType(Proposal::TYPE_OUTWARD);
            } else {
                $ad->setOneWay(true);
                $outwardProposal->setType(Proposal::TYPE_ONE_WAY);
            }
        } else {
            $outwardProposal->setType(Proposal::TYPE_OUTWARD);
        }

        // comment
        $outwardProposal->setComment($ad->getComment());

        // communities
        if ($ad->getCommunities()) {
            // todo : check if the user can post/search in each community
            foreach ($ad->getCommunities() as $communityId) {
                if ($community = $this->communityManager->getCommunity($communityId)) {
                    $outwardProposal->addCommunity($community);
                } else {
                    throw new CommunityNotFoundException('Community ' . $communityId . ' not found');
                }
            }
        }

        // event
        if ($ad->getEventId()) {
            if ($event = $this->eventManager->getEvent($ad->getEventId())) {
                $outwardProposal->setEvent($event);
            } else {
                throw new EventNotFoundException('Event ' . $ad->getEventId() . ' not found');
            }
        }
        
        // criteria

        // driver / passenger / seats
        $outwardCriteria->setDriver($ad->getRole() == Ad::ROLE_DRIVER || $ad->getRole() == Ad::ROLE_DRIVER_OR_PASSENGER);
        $outwardCriteria->setPassenger($ad->getRole() == Ad::ROLE_PASSENGER || $ad->getRole() == Ad::ROLE_DRIVER_OR_PASSENGER);
        $outwardCriteria->setSeatsDriver($ad->getSeatsDriver() ? $ad->getSeatsDriver() : $this->params['defaultSeatsDriver']);
        $outwardCriteria->setSeatsPassenger($ad->getSeatsPassenger() ? $ad->getSeatsPassenger() : $this->params['defaultSeatsPassenger']);

        // solidary
        $outwardCriteria->setSolidary($ad->isSolidary());
        $outwardCriteria->setSolidaryExclusive($ad->isSolidaryExclusive());

        // prices
        $outwardCriteria->setPriceKm($ad->getPriceKm());
        $outwardCriteria->setDriverPrice($ad->getOutwardDriverPrice());
        $outwardCriteria->setPassengerPrice($ad->getOutwardPassengerPrice());

        // strict
        $outwardCriteria->setStrictDate($ad->isStrictDate());
        $outwardCriteria->setStrictPunctual($ad->isStrictPunctual());
        $outwardCriteria->setStrictRegular($ad->isStrictRegular());

        // misc
        $outwardCriteria->setLuggage($ad->hasLuggage());
        $outwardCriteria->setBike($ad->hasBike());
        $outwardCriteria->setBackSeats($ad->hasBackSeats());

        // dates and times

        // if the date is not set we use the current date
        $outwardCriteria->setFromDate($ad->getOutwardDate() ? $ad->getOutwardDate() : new \DateTime());
        if ($ad->getFrequency() == Criteria::FREQUENCY_REGULAR) {
            $outwardCriteria->setFrequency(Criteria::FREQUENCY_REGULAR);
            $outwardCriteria->setToDate($ad->getOutwardLimitDate() ? \DateTime::createFromFormat('Y-m-d', $ad->getOutwardLimitDate()) : null);
            $hasSchedule = false;
            foreach ($ad->getSchedule() as $schedule) {
                if ($schedule['outwardTime'] != '') {
                    if (isset($schedule['mon']) && $schedule['mon']) {
                        $hasSchedule = true;
                        $outwardCriteria->setMonCheck(true);
                        $outwardCriteria->setMonTime(\DateTime::createFromFormat('H:i', $schedule['outwardTime']));
                        $outwardCriteria->setMonMarginDuration($this->params['defaultMarginTime']);
                    }
                    if (isset($schedule['tue']) && $schedule['tue']) {
                        $hasSchedule = true;
                        $outwardCriteria->setTueCheck(true);
                        $outwardCriteria->setTueTime(\DateTime::createFromFormat('H:i', $schedule['outwardTime']));
                        $outwardCriteria->setTueMarginDuration($this->params['defaultMarginTime']);
                    }
                    if (isset($schedule['wed']) && $schedule['wed']) {
                        $hasSchedule = true;
                        $outwardCriteria->setWedCheck(true);
                        $outwardCriteria->setWedTime(\DateTime::createFromFormat('H:i', $schedule['outwardTime']));
                        $outwardCriteria->setWedMarginDuration($this->params['defaultMarginTime']);
                    }
                    if (isset($schedule['thu']) && $schedule['thu']) {
                        $hasSchedule = true;
                        $outwardCriteria->setThuCheck(true);
                        $outwardCriteria->setThuTime(\DateTime::createFromFormat('H:i', $schedule['outwardTime']));
                        $outwardCriteria->setThuMarginDuration($this->params['defaultMarginTime']);
                    }
                    if (isset($schedule['fri']) && $schedule['fri']) {
                        $hasSchedule = true;
                        $outwardCriteria->setFriCheck(true);
                        $outwardCriteria->setFriTime(\DateTime::createFromFormat('H:i', $schedule['outwardTime']));
                        $outwardCriteria->setFriMarginDuration($this->params['defaultMarginTime']);
                    }
                    if (isset($schedule['sat']) && $schedule['sat']) {
                        $hasSchedule = true;
                        $outwardCriteria->setSatCheck(true);
                        $outwardCriteria->setsatTime(\DateTime::createFromFormat('H:i', $schedule['outwardTime']));
                        $outwardCriteria->setSatMarginDuration($this->params['defaultMarginTime']);
                    }
                    if (isset($schedule['sun']) && $schedule['sun']) {
                        $hasSchedule = true;
                        $outwardCriteria->setSunCheck(true);
                        $outwardCriteria->setSunTime(\DateTime::createFromFormat('H:i', $schedule['outwardTime']));
                        $outwardCriteria->setSunMarginDuration($this->params['defaultMarginTime']);
                    }
                }
            }
            if (!$hasSchedule && !$ad->isSearch()) {
                // for a post, we need aschedule !
                throw new AdException('At least one day should be selected for a regular trip');
            } elseif (!$hasSchedule) {
                // for a search we set the schedule to every day
                $outwardCriteria->setMonCheck(true);
                $outwardCriteria->setMonMarginDuration($this->params['defaultMarginTime']);
                $outwardCriteria->setTueCheck(true);
                $outwardCriteria->setTueMarginDuration($this->params['defaultMarginTime']);
                $outwardCriteria->setWedCheck(true);
                $outwardCriteria->setWedMarginDuration($this->params['defaultMarginTime']);
                $outwardCriteria->setThuCheck(true);
                $outwardCriteria->setThuMarginDuration($this->params['defaultMarginTime']);
                $outwardCriteria->setFriCheck(true);
                $outwardCriteria->setFriMarginDuration($this->params['defaultMarginTime']);
                $outwardCriteria->setSatCheck(true);
                $outwardCriteria->setSatMarginDuration($this->params['defaultMarginTime']);
                $outwardCriteria->setSunCheck(true);
                $outwardCriteria->setSunMarginDuration($this->params['defaultMarginTime']);
            }
        } else {
            // punctual
            $outwardCriteria->setFrequency(Criteria::FREQUENCY_PUNCTUAL);
            // if the time is not set we use the current time for an ad post, and null for a search
            $outwardCriteria->setFromTime($ad->getOutwardTime() ? \DateTime::createFromFormat('H:i', $ad->getOutwardTime()) : (!$ad->isSearch() ? new \DateTime() : null));
            $outwardCriteria->setMarginDuration($this->params['defaultMarginTime']);
        }

        // waypoints
        foreach ($ad->getOutwardWaypoints() as $position => $point) {
            $waypoint = new Waypoint();
            $address = new Address();
            if (isset($point['houseNumber'])) {
                $address->setHouseNumber($point['houseNumber']);
            }
            if (isset($point['street'])) {
                $address->setStreet($point['street']);
            }
            if (isset($point['streetAddress'])) {
                $address->setStreetAddress($point['streetAddress']);
            }
            if (isset($point['postalCode'])) {
                $address->setPostalCode($point['postalCode']);
            }
            if (isset($point['subLocality'])) {
                $address->setSubLocality($point['subLocality']);
            }
            if (isset($point['addressLocality'])) {
                $address->setAddressLocality($point['addressLocality']);
            }
            if (isset($point['localAdmin'])) {
                $address->setLocalAdmin($point['localAdmin']);
            }
            if (isset($point['county'])) {
                $address->setCounty($point['county']);
            }
            if (isset($point['macroCounty'])) {
                $address->setMacroCounty($point['macroCounty']);
            }
            if (isset($point['region'])) {
                $address->setRegion($point['region']);
            }
            if (isset($point['macroRegion'])) {
                $address->setMacroRegion($point['macroRegion']);
            }
            if (isset($point['addressCountry'])) {
                $address->setAddressCountry($point['addressCountry']);
            }
            if (isset($point['countryCode'])) {
                $address->setCountryCode($point['countryCode']);
            }
            if (isset($point['latitude'])) {
                $address->setLatitude($point['latitude']);
            }
            if (isset($point['longitude'])) {
                $address->setLongitude($point['longitude']);
            }
            if (isset($point['elevation'])) {
                $address->setElevation($point['elevation']);
            }
            if (isset($point['name'])) {
                $address->setName($point['name']);
            }
            if (isset($point['home'])) {
                $address->setHome($point['home']);
            }
            $waypoint->setAddress($address);
            $waypoint->setPosition($position);
            $waypoint->setDestination($position == count($ad->getOutwardWaypoints())-1);
            $outwardProposal->addWaypoint($waypoint);
        }

        $outwardProposal->setCriteria($outwardCriteria);
        $outwardProposal = $this->proposalManager->prepareProposal($outwardProposal);

        $this->entityManager->persist($outwardProposal);

        // return trip ?
        if (!$ad->isOneWay()) {
            // we clone the outward proposal
            $returnProposal = clone $outwardProposal;
            $returnProposal->setType(Proposal::TYPE_RETURN);
            
            // we link the outward and the return
            $outwardProposal->setProposalLinked($returnProposal);

            // criteria
            $returnCriteria = new Criteria();

            // driver / passenger / seats
            $returnCriteria->setDriver($outwardCriteria->isDriver());
            $returnCriteria->setPassenger($outwardCriteria->isPassenger());
            $returnCriteria->setSeatsDriver($outwardCriteria->getSeatsDriver());
            $returnCriteria->setSeatsPassenger($outwardCriteria->getSeatsPassenger());

            // solidary
            $returnCriteria->setSolidary($outwardCriteria->isSolidary());
            $returnCriteria->setSolidaryExclusive($outwardCriteria->isSolidaryExclusive());

            // prices
            $returnCriteria->setPriceKm($outwardCriteria->getPriceKm());
            $returnCriteria->setDriverPrice($ad->getReturnDriverPrice());
            $returnCriteria->setPassengerPrice($ad->getReturnPassengerPrice());

            // strict
            $returnCriteria->setStrictDate($outwardCriteria->isStrictDate());
            $returnCriteria->setStrictPunctual($outwardCriteria->isStrictPunctual());
            $returnCriteria->setStrictRegular($outwardCriteria->isStrictRegular());

            // misc
            $returnCriteria->setLuggage($outwardCriteria->hasLuggage());
            $returnCriteria->setBike($outwardCriteria->hasBike());
            $returnCriteria->setBackSeats($outwardCriteria->hasBackSeats());

            // dates and times
            // if no return date is specified, we use the outward date to be sure the return date is not before the outward date
            $returnCriteria->setFromDate($ad->getReturnDate() ? $ad->getReturnDate() : $outwardCriteria->getFromDate());
            if ($ad->getFrequency() == Criteria::FREQUENCY_REGULAR) {
                $returnCriteria->setFrequency(Criteria::FREQUENCY_REGULAR);
                $returnCriteria->setToDate($ad->getReturnLimitDate() ? \DateTime::createFromFormat('Y-m-d', $ad->getReturnLimitDate()) : null);
                $hasSchedule = false;
                foreach ($ad->getSchedule() as $schedule) {
                    if ($schedule['returnTime'] != '') {
                        if (isset($schedule['mon']) && $schedule['mon']) {
                            $hasSchedule = true;
                            $returnCriteria->setMonCheck(true);
                            $returnCriteria->setMonTime(\DateTime::createFromFormat('H:i', $schedule['returnTime']));
                            $returnCriteria->setMonMarginDuration($this->params['defaultMarginTime']);
                        }
                        if (isset($schedule['tue']) && $schedule['tue']) {
                            $hasSchedule = true;
                            $returnCriteria->setTueCheck(true);
                            $returnCriteria->setTueTime(\DateTime::createFromFormat('H:i', $schedule['returnTime']));
                            $returnCriteria->setTueMarginDuration($this->params['defaultMarginTime']);
                        }
                        if (isset($schedule['wed']) && $schedule['wed']) {
                            $hasSchedule = true;
                            $returnCriteria->setWedCheck(true);
                            $returnCriteria->setWedTime(\DateTime::createFromFormat('H:i', $schedule['returnTime']));
                            $returnCriteria->setWedMarginDuration($this->params['defaultMarginTime']);
                        }
                        if (isset($schedule['thu']) && $schedule['thu']) {
                            $hasSchedule = true;
                            $returnCriteria->setThuCheck(true);
                            $returnCriteria->setThuTime(\DateTime::createFromFormat('H:i', $schedule['returnTime']));
                            $returnCriteria->setThuMarginDuration($this->params['defaultMarginTime']);
                        }
                        if (isset($schedule['fri']) && $schedule['fri']) {
                            $hasSchedule = true;
                            $returnCriteria->setFriCheck(true);
                            $returnCriteria->setFriTime(\DateTime::createFromFormat('H:i', $schedule['returnTime']));
                            $returnCriteria->setFriMarginDuration($this->params['defaultMarginTime']);
                        }
                        if (isset($schedule['sat']) && $schedule['sat']) {
                            $hasSchedule = true;
                            $returnCriteria->setSatCheck(true);
                            $returnCriteria->setsatTime(\DateTime::createFromFormat('H:i', $schedule['returnTime']));
                            $returnCriteria->setSatMarginDuration($this->params['defaultMarginTime']);
                        }
                        if (isset($schedule['sun']) && $schedule['sun']) {
                            $hasSchedule = true;
                            $returnCriteria->setSunCheck(true);
                            $returnCriteria->setSunTime(\DateTime::createFromFormat('H:i', $schedule['returnTime']));
                            $returnCriteria->setSunMarginDuration($this->params['defaultMarginTime']);
                        }
                    }
                }
                if (!$hasSchedule && !$ad->isSearch()) {
                    // for a post, we need a schedule !
                    throw new AdException('At least one day should be selected for a regular trip');
                } elseif (!$hasSchedule) {
                    // for a search we set the schedule to every day
                    $returnCriteria->setMonCheck(true);
                    $returnCriteria->setMonMarginDuration($this->params['defaultMarginTime']);
                    $returnCriteria->setTueCheck(true);
                    $returnCriteria->setTueMarginDuration($this->params['defaultMarginTime']);
                    $returnCriteria->setWedCheck(true);
                    $returnCriteria->setWedMarginDuration($this->params['defaultMarginTime']);
                    $returnCriteria->setThuCheck(true);
                    $returnCriteria->setThuMarginDuration($this->params['defaultMarginTime']);
                    $returnCriteria->setFriCheck(true);
                    $returnCriteria->setFriMarginDuration($this->params['defaultMarginTime']);
                    $returnCriteria->setSatCheck(true);
                    $returnCriteria->setSatMarginDuration($this->params['defaultMarginTime']);
                    $returnCriteria->setSunCheck(true);
                    $returnCriteria->setSunMarginDuration($this->params['defaultMarginTime']);
                }
            } else {
                // punctual
                $returnCriteria->setFrequency(Criteria::FREQUENCY_PUNCTUAL);
                // if no return time is specified, we use the outward time to be sure the return date is not before the outward date, and null for a search
                $returnCriteria->setFromTime($ad->getReturnTime() ? \DateTime::createFromFormat('H:i', $ad->getReturnTime()) : (!$ad->isSearch() ? $outwardCriteria->getFromTime() : null));
                $returnCriteria->setMarginDuration($this->params['defaultMarginTime']);
            }

            // waypoints
            if (count($ad->getReturnWaypoints())==0) {
                // return waypoints are not set : we use the outward waypoints in reverse order
                $ad->setReturnWaypoints(array_reverse($ad->getOutwardWaypoints()));
            }
            foreach ($ad->getReturnWaypoints() as $position => $point) {
                $waypoint = new Waypoint();
                $address = new Address();
                if (isset($point['houseNumber'])) {
                    $address->setHouseNumber($point['houseNumber']);
                }
                if (isset($point['street'])) {
                    $address->setStreet($point['street']);
                }
                if (isset($point['streetAddress'])) {
                    $address->setStreetAddress($point['streetAddress']);
                }
                if (isset($point['postalCode'])) {
                    $address->setPostalCode($point['postalCode']);
                }
                if (isset($point['subLocality'])) {
                    $address->setSubLocality($point['subLocality']);
                }
                if (isset($point['addressLocality'])) {
                    $address->setAddressLocality($point['addressLocality']);
                }
                if (isset($point['localAdmin'])) {
                    $address->setLocalAdmin($point['localAdmin']);
                }
                if (isset($point['county'])) {
                    $address->setCounty($point['county']);
                }
                if (isset($point['macroCounty'])) {
                    $address->setMacroCounty($point['macroCounty']);
                }
                if (isset($point['region'])) {
                    $address->setRegion($point['region']);
                }
                if (isset($point['macroRegion'])) {
                    $address->setMacroRegion($point['macroRegion']);
                }
                if (isset($point['addressCountry'])) {
                    $address->setAddressCountry($point['addressCountry']);
                }
                if (isset($point['countryCode'])) {
                    $address->setCountryCode($point['countryCode']);
                }
                if (isset($point['latitude'])) {
                    $address->setLatitude($point['latitude']);
                }
                if (isset($point['longitude'])) {
                    $address->setLongitude($point['longitude']);
                }
                if (isset($point['elevation'])) {
                    $address->setElevation($point['elevation']);
                }
                if (isset($point['name'])) {
                    $address->setName($point['name']);
                }
                if (isset($point['home'])) {
                    $address->setHome($point['home']);
                }
                $waypoint->setAddress($address);
                $waypoint->setPosition($position);
                $waypoint->setDestination($position == count($ad->getReturnWaypoints())-1);
                $returnProposal->addWaypoint($waypoint);
            }

            $returnProposal->setCriteria($returnCriteria);
            $returnProposal = $this->proposalManager->prepareProposal($returnProposal);
            $this->entityManager->persist($returnProposal);
        }

        // we persist the proposals
        $this->entityManager->flush();

        $this->logger->info('Ad creation | End ' . (new \DateTime("UTC"))->format("Ymd H:i:s.u"));

        // if the ad is a round trip, we want to link the potential matching results
        if (!$ad->isOneWay()) {
            $outwardProposal = $this->proposalManager->linkRelatedMatchings($outwardProposal);
            $this->entityManager->persist($outwardProposal);
            $this->entityManager->flush();
            $this->logger->info('Ad creation | End flushing linking related ' . (new \DateTime("UTC"))->format("Ymd H:i:s.u"));
        }
        // if the requester can be driver and passenger, we want to link the potential opposite matching results
        if ($ad->getRole() == Ad::ROLE_DRIVER_OR_PASSENGER) {
            // linking for the outward
            $outwardProposal = $this->proposalManager->linkOppositeMatchings($outwardProposal);
            $this->entityManager->persist($outwardProposal);
            $this->logger->info('Ad creation | End linking opposite outward ' . (new \DateTime("UTC"))->format("Ymd H:i:s.u"));
            if (!$ad->isOneWay()) {
                // linking for the return
                $returnProposal = $this->proposalManager->linkOppositeMatchings($returnProposal);
                $this->entityManager->persist($returnProposal);
                $this->logger->info('Ad creation | End linking opposite return ' . (new \DateTime("UTC"))->format("Ymd H:i:s.u"));
            }
            $this->entityManager->flush();
            $this->logger->info('Ad creation | End flushing linking opposite ' . (new \DateTime("UTC"))->format("Ymd H:i:s.u"));
        }

        // we compute the results
        $this->logger->info('Ad creation | Start creation results  ' . (new \DateTime("UTC"))->format("Ymd H:i:s.u"));

        // default order
        $ad->setFilters([
                'order'=>[
                    'criteria'=>'date',
                    'value'=>'ASC'
                ]
            
        ]);

        $ad->setResults(
            $this->resultManager->orderResults(
                $this->resultManager->filterResults(
                    $this->resultManager->createAdResults($outwardProposal),
                    $ad->getFilters()
                ),
                $ad->getFilters()
            )
        );
        $this->logger->info('Ad creation | End creation results  ' . (new \DateTime("UTC"))->format("Ymd H:i:s.u"));

        // we set the ad id to the outward proposal id
        $ad->setId($outwardProposal->getId());

        return $ad;
    }

    /**
     * Get an ad.
     * Returns the ad, with its outward and return results.
     *
     * @param int $id       The ad id to get
     * @param array|null    The filters to apply to the results
     * @param array|null    The order to apply to the results
     * @return Ad
     */
    public function getAd(int $id, ?array $filters = null, ?array $order = null)
    {
        $ad = new Ad();
        $proposal = $this->proposalManager->get($id);
        $ad->setId($id);
        $ad->setFrequency($proposal->getCriteria()->getFrequency());
        $ad->setRole($proposal->getCriteria()->isDriver() ?  ($proposal->getCriteria()->isPassenger() ? Ad::ROLE_DRIVER_OR_PASSENGER : Ad::ROLE_DRIVER) : Ad::ROLE_PASSENGER);
        $ad->setSeatsDriver($proposal->getCriteria()->getSeatsDriver());
        $ad->setSeatsPassenger($proposal->getCriteria()->getSeatsPassenger());
        $ad->setUserId($proposal->getUser()->getId());
        $aFilters = [];
        if (!is_null($filters)) {
            $aFilters['filters']=$filters;
        }
        if (!is_null($order)) {
            $aFilters['order']=$order;
        }
        $ad->setFilters($aFilters);
        $ad->setResults(
            $this->resultManager->orderResults(
                $this->resultManager->filterResults(
                    $this->resultManager->createAdResults($proposal),
                    $ad->getFilters()
                ),
                $ad->getFilters()
            )
        );
        return $ad;
    }
}