<?php

namespace App\Incentive\Service\Stage;

use App\Carpool\Repository\CarpoolProofRepository;
use App\Incentive\Entity\Log\Log;
use App\Incentive\Entity\LongDistanceSubscription;
use App\Incentive\Entity\ShortDistanceSubscription;
use App\Incentive\Repository\LongDistanceJourneyRepository;
use App\Incentive\Resource\EecInstance;
use App\Incentive\Service\Manager\TimestampTokenManager;
use App\Incentive\Service\Provider\CarpoolPaymentProvider;
use App\Incentive\Validator\CarpoolPaymentValidator;
use App\Payment\Entity\CarpoolItem;
use App\Payment\Repository\CarpoolItemRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ProofRecovery extends Stage
{
    /**
     * @var CarpoolItemRepository
     */
    protected $_carpoolItemRepository;

    /**
     * @var CarpoolProofRepository
     */
    protected $_carpoolProofRepository;

    /**
     * @var LongDistanceJourneyRepository
     */
    protected $_longDistanceJourneyRepository;

    /**
     * @var User
     */
    protected $_user;

    /**
     * @var string
     */
    protected $_subscriptionType;

    public function __construct(
        EntityManagerInterface $em,
        CarpoolItemRepository $carpoolItemRepository,
        CarpoolProofRepository $carpoolProofRepository,
        LongDistanceJourneyRepository $longDistanceJourneyRepository,
        TimestampTokenManager $timestampTokenManager,
        EecInstance $eecInstance,
        User $user,
        string $subscriptionType
    ) {
        $this->_em = $em;
        $this->_carpoolItemRepository = $carpoolItemRepository;
        $this->_carpoolProofRepository = $carpoolProofRepository;
        $this->_longDistanceJourneyRepository = $longDistanceJourneyRepository;

        $this->_timestampTokenManager = $timestampTokenManager;
        $this->_eecInstance = $eecInstance;

        $this->_user = $user;
        $this->_subscriptionType = $subscriptionType;
    }

    public function execute()
    {
        // We recover the missing timestamp tokens available at moBConnect
        $this->_subscription = LongDistanceSubscription::TYPE_LONG === $this->_subscriptionType
            ? $this->_user->getLongDistanceSubscription() : $this->_user->getShortDistanceSubscription();

        $this->_timestampTokenManager->setMissingSubscriptionTimestampTokens($this->_subscription, Log::TYPE_VERIFY);

        $this->_recoveryProofs();
    }

    protected function _recoveryProofs()
    {
        switch (true) {
            case $this->_subscription instanceof LongDistanceSubscription:
                /**
                 * @var CarpoolItem[]
                 */
                $carpoolItems = $this->_carpoolItemRepository->findUserEECEligibleItem($this->_user);

                foreach ($carpoolItems as $carpoolItem) {
                    if (
                        is_null($this->_subscription->getCommitmentProofDate())
                        && empty($this->_subscription->getJourneys())
                    ) {
                        $proposal = $carpoolItem->getProposalAccordingUser($this->_user);

                        $subscription = !is_null($proposal->getUser()) && !is_null($proposal->getUser()->getLongDistanceSubscription())
                            ? $proposal->getUser()->getLongDistanceSubscription()
                            : null;

                        if (is_null($subscription)) {
                            return null;
                        }

                        $stage = new CommitLDSubscription($this->_em, $this->_timestampTokenManager, $this->_eecInstance, $subscription, $proposal);
                        $stage->execute();

                        return;
                    }

                    $carpoolPayment = CarpoolPaymentProvider::getCarpoolPaymentFromCarpoolItem($carpoolItem);

                    if (!is_null($carpoolPayment) && CarpoolPaymentValidator::isStatusEecCompliant($carpoolPayment)) {
                        $stage = new ValidateLDSubscription($this->_em, $this->_longDistanceJourneyRepository, $this->_timestampTokenManager, $this->_eecInstance, $carpoolPayment);
                        $stage->execute();
                    }
                }

                break;

            case $this->_subscription instanceof ShortDistanceSubscription:
                $carpoolProofs = $this->_carpoolProofRepository->findUserCEEEligibleProof($this->_user, $this->_subscriptionType);

                foreach ($carpoolProofs as $carpoolProof) {
                    if (
                        is_null($this->_subscription->getCommitmentProofDate())
                        && empty($this->_subscription->getJourneys())
                    ) {
                        $subscription = !is_null($carpoolProof->getDriver()) && !is_null($carpoolProof->getDriver()->getShortDistanceSubscription())
                            ? $carpoolProof->getDriver()->getShortDistanceSubscription()
                            : null;

                        if (is_null($subscription)) {
                            return null;
                        }

                        $stage = new CommitSDSubscription($this->_em, $this->_timestampTokenManager, $this->_eecInstance, $subscription, $carpoolProof);
                        $stage->execute();

                        return;
                    }

                    $stage = new ProofValidate($this->_em, $this->_longDistanceJourneyRepository, $this->_timestampTokenManager, $this->_eecInstance, $carpoolProof);
                    $stage->execute();
                }

                break;
        }
    }
}