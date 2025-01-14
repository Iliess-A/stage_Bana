<?php

namespace App\Incentive\Service\Validation;

use App\Carpool\Entity\CarpoolProof;
use App\Carpool\Entity\Matching;
use App\Carpool\Entity\Proposal;
use App\Incentive\Entity\LongDistanceJourney;
use App\Incentive\Entity\ShortDistanceJourney;
use App\Incentive\Resource\CeeSubscriptions;
use App\Incentive\Service\LoggerService;
use App\Payment\Entity\CarpoolItem;
use App\User\Entity\User;

abstract class Validation
{
    public const LONG_DISTANCE_THRESHOLD = CeeSubscriptions::LONG_DISTANCE_MINIMUM_IN_METERS;
    public const REFERENCE_COUNTRY = 'France';

    public const REFERENCE_DATE = '2023-01-01';
    public const REFERENCE_PERIOD = 3;                   // Period expressed in years

    /**
     * @var User
     */
    protected $_driver;

    /**
     * @var LoggerService
     */
    protected $_loggerService;

    /**
     * @var TokenStorageInterface
     */
    protected $_tokenStorage;

    public function __construct(LoggerService $loggerService)
    {
        $this->_loggerService = $loggerService;
    }

    public function isDistanceLongDistance(int $distance): bool
    {
        return self::LONG_DISTANCE_THRESHOLD <= $distance;
    }

    public function isOriginOrDestinationFromFrance($journey): bool
    {
        switch (true) {
            case $journey instanceof CarpoolItem:
                return $this->_isOriginOrDestinationFromFranceForCarpoolItem($journey);

            case $journey instanceof CarpoolProof:
                return $this->_isOriginOrDestinationFromFranceForCarpoolProof($journey);

            case $journey instanceof Matching:
                return $this->_isOriginOrDestinationFromFranceForMatching($journey);

            case $journey instanceof Proposal:
                return $this->_isOriginOrDestinationFromFranceForProposal($journey);

            default:
                throw new \LogicException('The class '.get_class($journey).' cannot be processed');
        }
    }

    public function hasValidMobConnectAuth(?User $user): bool
    {
        /**
         * @var User $requester
         */
        $requester = is_null($user) ? $this->_tokenStorage->getToken()->getUser() : $user;

        return
            !is_null($requester->getMobConnectAuth())
            && $requester->getMobConnectAuth()->isValid();
    }

    public function isUserValid(User $user): bool
    {
        $this->setDriver($user);

        return
            !is_null($this->getDriver())
            && !is_null($this->getDriver()->getDrivingLicenceNumber())
            && !is_null($this->getDriver()->getTelephone())
            && !is_null($this->getDriver()->getPhoneValidatedDate())
            && $this->hasValidMobConnectAuth($this->getDriver());
    }

    protected function _hasLongDistanceJourneyAlreadyDeclared(CarpoolItem $carpoolItem): bool
    {
        $filteredLongDistanceJourney = array_filter(
            $this->_driver->getLongDistanceSubscription()->getJourneys()->toArray(),
            function (LongDistanceJourney $journey) use ($carpoolItem) {
                return !is_null($journey->getCarpoolItem()) && $journey->getCarpoolItem()->getId() === $carpoolItem->getId();
            }
        );

        return !empty($filteredLongDistanceJourney);
    }

    protected function _hasShortDistanceJourneyAlreadyDeclared(CarpoolProof $carpoolProof): bool
    {
        $filteredShortDistanceJourney = array_filter(
            $this->_driver->getShortDistanceSubscription()->getJourneys()->toArray(),
            function (ShortDistanceJourney $journey) use ($carpoolProof) {
                return $journey->getCarpoolProof()->getId() === $carpoolProof->getId();
            }
        );

        return !empty($filteredShortDistanceJourney);
    }

    protected function isDateAfterReferenceDate(\DateTime $date): bool
    {
        return new \DateTime(self::REFERENCE_DATE) <= $date;
    }

    protected static function isDateInPeriod(\DateTime $dateToCheck): bool
    {
        $dateEndPeriod = new \DateTime('now');
        $dateStartPeriod = clone $dateEndPeriod;
        $dateStartPeriod = $dateStartPeriod->sub(new \DateInterval('P'.self::REFERENCE_PERIOD.'M'));

        return $dateStartPeriod <= $dateToCheck && $dateToCheck <= $dateEndPeriod;
    }

    protected function getDriver(): ?User
    {
        return $this->_driver;
    }

    protected function setDriver(User $driver): self
    {
        $this->_driver = $driver;

        if (is_null($this->_driver)) {
            $this->_loggerService->log('The proof must have a driver');
        }

        return $this;
    }

    private function _isOriginOrDestinationFromFranceForCarpoolItem(CarpoolItem $carpoolItem): bool
    {
        return $this->_isOriginOrDestinationFromFranceForMatching($carpoolItem->getAsk()->getMatching());
    }

    private function _isOriginOrDestinationFromFranceForCarpoolProof(CarpoolProof $carpoolProof): bool
    {
        return $this->_isOriginOrDestinationFromFranceForMatching($carpoolProof->getAsk()->getMatching());
    }

    private function _isOriginOrDestinationFromFranceForMatching(Matching $matching): bool
    {
        return $this->_isOriginOrDestinationFromFranceForWaypoints($matching->getWaypoints());
    }

    private function _isOriginOrDestinationFromFranceForProposal(Proposal $proposal): bool
    {
        return $this->_isOriginOrDestinationFromFranceForWaypoints($proposal->getWaypoints());
    }

    private function _isOriginOrDestinationFromFranceForWaypoints($waypoints): bool
    {
        if (empty($waypoints)) {
            return false;
        }

        foreach ($waypoints as $waypoint) {
            if (
                !is_null($waypoint->getAddress())
                && !is_null($waypoint->getAddress()->getAddressCountry())
                && self::REFERENCE_COUNTRY === $waypoint->getAddress()->getAddressCountry()
            ) {
                return true;
            }
        }

        return false;
    }
}
