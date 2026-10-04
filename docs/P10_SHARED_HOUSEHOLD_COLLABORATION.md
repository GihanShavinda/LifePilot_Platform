# P10 — Shared Household Collaboration

## Architecture

P10 separates **household membership** from **resource visibility**.

```text
Authenticated User
      ↓
Household Membership + Role
      ↓
Resource Ownership
      ↓
Explicit SharedResource Scope
      ↓
Read / Write authorization
      ↓
Controller / Search / AI / Agentic workflow
```

`SharedResource` is a permission overlay. Existing domain rows continue to use their current `household_id` and `user_id`; P10 does not silently convert household rows into shared rows.

## Entities

### HouseholdInvitation
Stores a hashed invitation token, target email, target role, expiry, and response state. Plaintext tokens are never persisted.

### SharedResource
Maps one supported domain resource to either `private` or `household` scope. Missing rows are interpreted as private.

### Assignment
Currently supports shared task assignment. Creating an assignment explicitly changes that task to household scope first.

### HouseholdActivity
Provides an auditable collaboration feed and can fan out an in-app household notification.

## Access policy

### Read
A resource can be read when:

1. the user has a membership in the resource household; and
2. the user owns the resource, **or** the resource has explicit household scope.

### Write
A resource can be changed when:

1. the read test succeeds; and
2. the member role is Owner, Admin, Member, or legacy Family Member.

Viewer is read-only.

### Sharing changes
Only the resource owner or a household Owner/Admin can change sharing scope.

## AI / search isolation

P10 modifies the P8 evidence bundle and P7 search/graph response layers. Private records owned by another household member are excluded before they reach the LLM or the user-facing semantic search response.

Accepted extraction fields are retrieved only through accessible documents. A household membership by itself is never sufficient to retrieve another member's private document facts.

P9 action evidence validation also uses the same resource visibility checks. Agentic update/archive/categorize/reminder actions additionally enforce write access at execution time.

## Removal behavior

Removing a member:

- deletes that HouseholdMember row;
- removes assignments where that user is the assignee;
- immediately prevents them from reading household-shared resources because access always requires current membership;
- does not delete another user's resources;
- does not convert private resources into shared resources.

The Owner cannot be removed through the P10 endpoint. Admins cannot remove another Admin; that requires the Owner.
