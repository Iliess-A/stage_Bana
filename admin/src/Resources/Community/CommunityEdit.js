import React, { useState } from 'react';

import {
  Edit,
  TabbedForm,
  FormTab,
  required,
  Link,
  Datagrid,
  TextInput,
  DateInput,
  BooleanInput,
  ReferenceInput,
  SelectInput,
  FunctionField,
  ReferenceField,
  ReferenceArrayField,
  Button,
  DeleteButton,
  useTranslate,
  useRedirect,
  List,
} from 'react-admin';

import RichTextInput from 'ra-input-rich-text';
import { makeStyles } from '@material-ui/core/styles';
import AddIcon from '@material-ui/icons/Add';

import { UserRenderer, addressRenderer } from '../../utils/renderers';
import GeocompleteInput from '../../components/geolocation/geocomplete';
import { validationChoices } from './communityChoices';
import UserReferenceField from '../User/UserReferenceField';
import SelectNewStatus from '../CommunityUser/SelectNewStatus';

const useStyles = makeStyles({
  hiddenField: { display: 'none' },
  fullwidth: { width: '100%', marginBottom: '1rem' },
  fullwidthDense: { width: '100%' },
  richtext: { width: '100%', minHeight: '15rem', marginBottom: '1rem' },
  title: { fontSize: '1.5rem', fontWeight: 'bold', width: '100%', marginBottom: '1rem' },
  inlineBlock: { display: 'inline-flex', marginRight: '1rem' },
});

const AddNewMemberButton = ({ record }) => {
  const classes = useStyles();
  return (
    <Link
      to={{
        pathname: `/community_users/create`,
        search: `?community=${record.originId}`,
      }}
    >
      <Button label="Ajouter un membre" startIcon={<AddIcon />} className={classes.actionButton} />
    </Link>
  );
};

export const CommunityEdit = (props) => {
  const classes = useStyles();
  const redirect = useRedirect();
  const translate = useTranslate();
  const communityId = props.id;

  const roles = Array.isArray(localStorage.roles)
    ? localStorage.roles.split(',')
    : localStorage.roles;
  // eslint-disable-next-line no-unused-vars
  const [communityManager, setCommunityManager] = useState(
    !roles.includes('ROLE_SUPER_ADMIN') && !roles.includes('ROLE_ADMIN') ? true : false
  );

  return (
    <Edit {...props} title="Communautés > éditer">
      <TabbedForm>
        <FormTab label={translate('custom.label.community.community')}>
          <TextInput
            fullWidth
            source="name"
            label={translate('custom.label.community.name')}
            validate={required()}
            formClassName={classes.title}
          />
          <TextInput disabled source="originId" formClassName={classes.hiddenField} />
          <ReferenceField
            source="address"
            label={translate('custom.label.community.oldAdress')}
            reference="addresses"
            link=""
            formClassName={classes.fullwidthDense}
          >
            <FunctionField render={addressRenderer} />
          </ReferenceField>
          <GeocompleteInput
            source="address"
            label={translate('custom.label.community.newAdress')}
            validate={required()}
            formClassName={classes.fullwidth}
          />

          <BooleanInput
            source="membersHidden"
            label={translate('custom.label.community.memberHidden')}
            formClassName={classes.inlineBlock}
          />
          <BooleanInput
            source="proposalsHidden"
            label={translate('custom.label.community.proposalHidden')}
            formClassName={classes.inlineBlock}
          />
          <SelectInput
            source="validationType"
            label={translate('custom.label.community.validationType')}
            choices={validationChoices}
            formClassName={classes.inlineBlock}
          />
          <TextInput
            fullWidth
            source="domain"
            label={translate('custom.label.community.domainName')}
          />

          <TextInput
            fullWidth
            source="description"
            label={translate('custom.label.community.description')}
            validate={required()}
            formClassName={classes.fullwidth}
          />
          <RichTextInput
            variant="filled"
            source="fullDescription"
            label={translate('custom.label.community.descriptionFull')}
            validate={required()}
            formClassName={classes.richtext}
          />

          <DateInput
            disabled
            source="createdDate"
            label={translate('custom.label.community.createdDate')}
            formClassName={classes.inlineBlock}
          />
          <DateInput
            disabled
            source="updatedDate"
            label={translate('custom.label.community.updateDate')}
            formClassName={classes.inlineBlock}
          />
          <TextInput
            disabled
            source="status"
            label={translate('custom.label.community.status')}
            formClassName={classes.inlineBlock}
          />
          <ReferenceInput
            disabled
            source="user"
            label={translate('custom.label.community.createdBy')}
            reference="users"
            formClassName={classes.inlineBlock}
          >
            <SelectInput optionText={<UserRenderer />} />
          </ReferenceInput>
        </FormTab>
        <FormTab label={translate('custom.label.community.members')}>
          {!communityManager && <AddNewMemberButton />}
          <ReferenceArrayField
            fullWidth
            source="communityUsers"
            reference="community_users"
            label="Tags"
          >
            <Datagrid>
              <UserReferenceField
                label={translate('custom.label.community.member')}
                source="user"
                sortBy="user.givenName"
                reference="users"
              />

              <SelectNewStatus label={translate('custom.label.community.newStatus')} />
              <DeleteButton
                onClick={() => redirect('edit', '/communities', encodeURIComponent(communityId))}
              />
            </Datagrid>
          </ReferenceArrayField>
        </FormTab>
      </TabbedForm>
    </Edit>
  );
};