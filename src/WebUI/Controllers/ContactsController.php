<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Models\Contact;
use Cardy\WebUI\Controller;

class ContactsController extends Controller
{
    public function index(): void
    {
        $user     = $this->requireAuth();
        $search   = trim($_GET['q'] ?? '');
        $contacts = Contact::allForUser($user['username'], $search);

        $this->render('contacts/index', [
            'user'     => $user,
            'contacts' => $contacts,
            'search'   => $search,
            'csrf'     => $this->csrfToken(),
            'flash'    => $this->getFlash(),
        ]);
    }

    public function view(array $params): void
    {
        $user    = $this->requireAuth();
        $contact = Contact::findById((int) $params['id'], $user['username']);
        if (!$contact) {
            $this->abort(404, 'Contact not found.');
        }

        $this->render('contacts/view', [
            'user'    => $user,
            'contact' => $contact,
            'csrf'    => $this->csrfToken(),
        ]);
    }

    public function create(): void
    {
        $user = $this->requireAuth();
        $this->render('contacts/form', [
            'user'    => $user,
            'contact' => null,
            'csrf'    => $this->csrfToken(),
        ]);
    }

    public function store(): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();

        $data = $this->extractFormData();

        try {
            Contact::create($user['username'], $data);
            $this->flash('success', 'Contact created successfully.');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to create contact: ' . $e->getMessage());
        }

        $this->redirect('/contacts');
    }

    public function edit(array $params): void
    {
        $user    = $this->requireAuth();
        $contact = Contact::findById((int) $params['id'], $user['username']);
        if (!$contact) {
            $this->abort(404, 'Contact not found.');
        }

        $this->render('contacts/form', [
            'user'    => $user,
            'contact' => $contact,
            'csrf'    => $this->csrfToken(),
        ]);
    }

    public function update(array $params): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();

        $contact = Contact::findById((int) $params['id'], $user['username']);
        if (!$contact) {
            $this->abort(404, 'Contact not found.');
        }

        $data = $this->extractFormData();
        $data['uid'] = $contact['uid'];

        try {
            Contact::update((int) $params['id'], $user['username'], $data);
            $this->flash('success', 'Contact updated successfully.');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to update contact: ' . $e->getMessage());
        }

        $this->redirect('/contacts/' . $params['id']);
    }

    public function delete(array $params): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();

        Contact::delete((int) $params['id'], $user['username']);
        $this->flash('success', 'Contact deleted.');
        $this->redirect('/contacts');
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function extractFormData(): array
    {
        $post = $_POST;

        $emails = [];
        foreach (['email1', 'email2', 'email3'] as $i => $key) {
            if (!empty($post[$key])) {
                $emails[] = [
                    'address' => $post[$key],
                    'type'    => $post["email{$i}_type"] ?? 'internet',
                ];
            }
        }

        $phones = [];
        foreach (['phone1', 'phone2', 'phone3'] as $i => $key) {
            if (!empty($post[$key])) {
                $phones[] = [
                    'number' => $post[$key],
                    'type'   => $post["phone{$i}_type"] ?? 'voice',
                ];
            }
        }

        $addresses = [];
        if (!empty($post['home_street']) || !empty($post['home_city'])) {
            $addresses[] = [
                'type'     => 'home',
                'street'   => $post['home_street']   ?? '',
                'city'     => $post['home_city']     ?? '',
                'region'   => $post['home_region']   ?? '',
                'postcode' => $post['home_postcode']  ?? '',
                'country'  => $post['home_country']  ?? '',
            ];
        }
        if (!empty($post['work_street']) || !empty($post['work_city'])) {
            $addresses[] = [
                'type'     => 'work',
                'street'   => $post['work_street']   ?? '',
                'city'     => $post['work_city']     ?? '',
                'region'   => $post['work_region']   ?? '',
                'postcode' => $post['work_postcode']  ?? '',
                'country'  => $post['work_country']  ?? '',
            ];
        }

        return [
            'first_name'  => $post['first_name']  ?? '',
            'last_name'   => $post['last_name']   ?? '',
            'org'         => $post['org']         ?? '',
            'title'       => $post['title']       ?? '',
            'birthday'    => $post['birthday']    ?? '',
            'note'        => $post['note']        ?? '',
            'emails'      => $emails,
            'phones'      => $phones,
            'addresses'   => $addresses,
            'uid'         => $post['uid']         ?? '',
        ];
    }
}
