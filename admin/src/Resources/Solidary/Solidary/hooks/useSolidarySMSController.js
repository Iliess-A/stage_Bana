import { useEffect, useState } from 'react';
import { useMutation, useDataProvider } from 'react-admin';

export const useSolidarySMSController = (solidaryId, solidarySolutionId) => {
  const dataProvider = useDataProvider();
  const [loading, setLoading] = useState(false);
  const [beneficiary, setBeneficiary] = useState(null);
  const [send, { loading: submitting }] = useMutation({});

  useEffect(() => {
    setLoading(true);
    dataProvider
      .getOne('solidaries', { id: `/solidaries/${solidaryId}` })
      .then(async ({ data }) => {
        if (data.solidaryUser && data.solidaryUser.user) {
          setBeneficiary(data.solidaryUser.user);
        }
      })
      .catch(() => {
        /* Just ignore error */
      })
      .finally(() => setLoading(false));
  }, [solidaryId]);

  const handleSubmit = (content) => {
    send({
      type: 'create',
      resource: 'solidary_contacts',
      payload: {
        data: {
          solidarySolution: `/solidary_solutions/${solidarySolutionId}`,
          media: ['/media/3'], // "/media/3" is the media type for SMS
          content,
        },
      },
    });
  };

  return {
    data: { beneficiary },
    submit: handleSubmit,
    submitting,
    loading,
  };
};