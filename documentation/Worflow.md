
# Chatwoot Integration
A conversation or started in chatwoot
The digital responder in chatwoot responds to the conversation

## Ready for follow
The conversation is continued in chat loop until The digital responder determines that the contact is ready to be transferred to disciple.tools. 
The digital responder presses the [] macro Button
- Sync to D.T
- Transfer to D.T
- Ready for follow-up

## Macro button -> D.T
The macro calls the Chatwoot sync endpoint in disciple.tools 
A D.T contact record is created for the chatwoot contact.
- If the contact has a phone or email, then we can look for existing D.T contact records.

The chatwood conversation with all the messages is saved to a D.T conversation Record and linked to the D.T Contact.

The D.T contact id, conversation id, conversation url and contact url are saved to chatwoot.
(Maybe not all 4 of those are needed).
This will let the Digital Responder jump back to D.T to add more information if needed.

The link to the chatwoot conversation is stored on the D.T conversation record.

## New messages
When new messages come in to chatwoot, the message_created webhook is fired.
D.T listens for the webhook and saves new messages for conversations that are already in D.T.





# Use cases
Online media -> D.T for sending bibles
Online media -> handoff to D.T for inperson follow-up
