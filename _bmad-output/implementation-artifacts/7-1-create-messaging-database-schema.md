# Story 7.1: Create Messaging Database Schema

Status: done

## Story

As a **developer**,
I want **the conversations and messages tables**,
so that **chat functionality can be stored and retrieved**.

## Acceptance Criteria

1. **Given** the database migration runs **When** the conversations table is created **Then** it includes all required fields: id, candidature_id, timestamps

2. **Given** a conversation is created **When** candidature_id is set **Then** the foreign key to candidatures table is properly indexed and constrained with cascade delete

3. **Given** the database migration runs **When** the messages table is created **Then** it includes all required fields: id, conversation_id, sender_id, sender_type, content, read_at, timestamps

4. **Given** a message is created **When** conversation_id is set **Then** the foreign key to conversations table is properly indexed and constrained with cascade delete

5. **Given** a message is created **When** sender_id and sender_type are set **Then** the polymorphic relationship to User is properly defined

6. **Given** a candidature has status 'accepted' **When** queried **Then** only one conversation can exist per candidature (unique constraint)

7. **Given** the Conversation model exists **When** queried **Then** relationships to Candidature and Messages are properly defined

8. **Given** the Message model exists **When** queried **Then** relationships to Conversation and User (sender) are properly defined

9. **Given** a user queries their conversations **When** the query runs **Then** it can efficiently filter by participant using the candidature relationship

**(Implements FR42, FR43, FR44, FR45 foundation)**

## Tasks / Subtasks

- [x] Task 1: Create conversations table migration (AC: #1, #2, #6)
  - [x] Create migration file `create_conversations_table.php`
  - [x] Add id, candidature_id (unique), timestamps columns
  - [x] Add foreign key constraint to `candidatures` table with cascade delete
  - [x] Add unique constraint on candidature_id (one conversation per candidature)

- [x] Task 2: Create messages table migration (AC: #3, #4, #5)
  - [x] Create migration file `create_messages_table.php`
  - [x] Add id, conversation_id, sender_id, sender_type, content (text), read_at (nullable timestamp), timestamps
  - [x] Add foreign key constraint to `conversations` table with cascade delete
  - [x] Add index on (conversation_id, created_at) for efficient message ordering
  - [x] Add index on sender (polymorphic: sender_type, sender_id)

- [x] Task 3: Create Conversation model (AC: #7)
  - [x] Create `app/Models/Conversation.php`
  - [x] Define `$fillable` with: candidature_id
  - [x] Add `candidature()` BelongsTo relationship
  - [x] Add `messages()` HasMany relationship
  - [x] Add `face()` accessor through candidature relationship
  - [x] Add `producer()` accessor through candidature->mission relationship
  - [x] Add `latestMessage()` relationship for list views
  - [x] Add `unreadCount()` method for notification badges

- [x] Task 4: Create Message model (AC: #8)
  - [x] Create `app/Models/Message.php`
  - [x] Define `$fillable` with: conversation_id, sender_id, sender_type, content, read_at
  - [x] Define `$casts` for read_at as datetime
  - [x] Add `conversation()` BelongsTo relationship
  - [x] Add `sender()` MorphTo relationship
  - [x] Add `markAsRead()` method
  - [x] Add `scopeUnread()` scope
  - [x] Add `scopeRead()` scope

- [x] Task 5: Update Candidature model (AC: #7)
  - [x] Add `conversation()` HasOne relationship to Candidature model
  - [x] Add `hasConversation()` helper method

- [x] Task 6: Update User model (AC: #8)
  - [x] Add `sentMessages()` MorphMany relationship to User model

- [x] Task 7: Create Conversation factory (for testing)
  - [x] Create `database/factories/ConversationFactory.php`
  - [x] Use existing CandidatureFactory for relationship
  - [x] Create candidatures with 'accepted' status by default (chat access rule)

- [x] Task 8: Create Message factory (for testing)
  - [x] Create `database/factories/MessageFactory.php`
  - [x] Use existing ConversationFactory and UserFactory for relationships
  - [x] Add states for read/unread messages
  - [x] Add realistic message content generation

- [x] Task 9: Create feature tests (AC: #1-9)
  - [x] Test migration creates conversations table with all columns
  - [x] Test foreign key constraint to candidatures works correctly
  - [x] Test unique constraint on candidature_id prevents duplicates
  - [x] Test migration creates messages table with all columns
  - [x] Test foreign key constraint to conversations works correctly
  - [x] Test polymorphic sender relationship works
  - [x] Test Conversation->candidature relationship
  - [x] Test Conversation->messages relationship
  - [x] Test Conversation->face accessor
  - [x] Test Conversation->producer accessor
  - [x] Test Message->conversation relationship
  - [x] Test Message->sender polymorphic relationship
  - [x] Test Message markAsRead() method
  - [x] Test Message unread/read scopes
  - [x] Test cascade delete from candidature to conversation to messages

## Dev Notes

### CRITICAL BUSINESS RULES (from project-context.md)

**Chat Access Rule**: Chat is unlocked ONLY after candidature status = 'accepted' (FR42, FR43)
- Conversation creation MUST be tied to candidature acceptance
- Story 7.2 will implement the automatic conversation creation on accept
- This schema story just provides the foundation

**Architecture Decision**: One conversation per candidature, not per mission
- Rationale: A Face can apply to multiple missions from same Producer
- Each accepted candidature = one distinct conversation about that specific mission

### Database Design Rationale

**Why candidature_id on conversations (not face_id + producer_id)?**
1. **Business logic**: Chat is about a specific mission context, not general contact
2. **Access control**: Easier to verify chat access by checking candidature.status
3. **Data integrity**: Conversation automatically scoped to accepted candidature
4. **Query efficiency**: Direct path: conversation → candidature → mission/face/producer

**Why polymorphic sender on messages?**
- Both Face and Producer can send messages
- User model is the parent of both (via userable relationship)
- Allows querying "all messages sent by this user" regardless of role

### Database Schema Details

```sql
-- conversations table
CREATE TABLE conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    candidature_id BIGINT UNSIGNED NOT NULL UNIQUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (candidature_id) REFERENCES candidatures(id) ON DELETE CASCADE
);

-- messages table
CREATE TABLE messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    sender_type VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    INDEX (conversation_id, created_at),
    INDEX (sender_type, sender_id)
);
```

### Migration Code Pattern

```php
// create_conversations_table.php
Schema::create('conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('candidature_id')
        ->unique()
        ->constrained('candidatures')
        ->cascadeOnDelete();
    $table->timestamps();
});

// create_messages_table.php
Schema::create('messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')
        ->constrained('conversations')
        ->cascadeOnDelete();
    $table->morphs('sender'); // Creates sender_id and sender_type
    $table->text('content');
    $table->timestamp('read_at')->nullable();
    $table->timestamps();

    // Index for efficient message retrieval
    $table->index(['conversation_id', 'created_at']);
});
```

### Model Patterns

**Conversation Model:**
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidature_id',
    ];

    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Get the Face participant through candidature.
     */
    public function getFaceAttribute(): ?Face
    {
        return $this->candidature?->face;
    }

    /**
     * Get the Producer participant through candidature->mission.
     */
    public function getProducerAttribute(): ?Producer
    {
        return $this->candidature?->mission?->producer;
    }

    /**
     * Get unread message count for a specific user.
     */
    public function unreadCountFor(User $user): int
    {
        return $this->messages()
            ->whereNull('read_at')
            ->where(function ($query) use ($user) {
                $query->where('sender_id', '!=', $user->id)
                    ->orWhere('sender_type', '!=', get_class($user));
            })
            ->count();
    }
}
```

**Message Model:**
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_type',
        'content',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Mark this message as read.
     */
    public function markAsRead(): bool
    {
        if ($this->read_at !== null) {
            return false;
        }

        return $this->update(['read_at' => now()]);
    }

    /**
     * Check if message is read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Scope for unread messages.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope for read messages.
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }
}
```

### Project Structure (Files to Create)

```
backend/
├── app/
│   └── Models/
│       ├── Conversation.php
│       └── Message.php
├── database/
│   ├── factories/
│   │   ├── ConversationFactory.php
│   │   └── MessageFactory.php
│   └── migrations/
│       ├── 2026_xx_xx_xxxxxx_create_conversations_table.php
│       └── 2026_xx_xx_xxxxxx_create_messages_table.php
└── tests/
    └── Feature/
        └── Messaging/
            └── MessagingSchemaTest.php
```

### Files to Modify

```
backend/app/Models/Candidature.php   # Add conversation() HasOne relationship
backend/app/Models/User.php          # Add sentMessages() MorphMany relationship
```

### Existing Patterns to Follow

**From Story 6-1 (Candidature Schema):**
- Migration pattern with foreign keys and cascade delete
- Model pattern with relationships, casts, and scopes
- Factory pattern with states for testing
- Comprehensive feature tests for all acceptance criteria

**From Story 6-9 (Notifications):**
- Similar morphs pattern for sender relationship
- read_at timestamp pattern for marking as read

### Testing Standards

- Use `RefreshDatabase` trait
- Use PHPUnit class-based syntax (project standard): `$this->assertTrue()`, `$this->assertEquals()`
- Test all 9 acceptance criteria
- Test cascade delete behavior (candidature → conversation → messages)
- Test polymorphic sender relationship works for both Face and Producer
- Verify unique constraint throws exception on duplicate candidature_id
- All existing tests must pass (currently 620 backend tests)

### MVP vs V2 Considerations

**MVP (This Story + Epic 7):**
- Database schema ready for all messaging features
- Manual refresh (polling 30s) for message updates
- read_at for basic read receipts

**V2 (Future):**
- WebSockets via Laravel Echo + Pusher for real-time
- Typing indicators (new columns possible)
- Message reactions (separate table possible)
- File attachments in messages (media_path column possible)

### Dependencies

- **Depends on**: Story 6-1 (Candidature model and table must exist) ✓ COMPLETED
- **Blocks**: Stories 7-2 through 7-7 (all messaging feature stories)

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 7.1 - Create Messaging Database Schema]
- [Source: _bmad-output/planning-artifacts/epics.md#Epic 7 - Messaging System (FR42-FR45)]
- [Source: _bmad-output/project-context.md#Critical Business Rules - Chat access: Only unlocked AFTER candidature is accepted]
- [Source: _bmad-output/project-context.md#Database Naming - snake_case conventions]
- [Source: backend/app/Models/Candidature.php - Parent model to extend]
- [Source: backend/app/Models/Notification.php - Polymorphic pattern reference]
- [Source: _bmad-output/implementation-artifacts/6-1-create-candidature-database-schema.md - Similar schema story pattern]
- [Source: docs/planning-artifacts/architecture.md#API & Communication Patterns - Polling MVP → WebSockets V2]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.5 (claude-opus-4-5-20251101)

### Debug Log References

No issues encountered during implementation.

### Completion Notes List

- Created conversations table migration with unique candidature_id foreign key and cascade delete
- Created messages table migration with polymorphic sender, cascade delete, and composite indexes
- Created Conversation model with BelongsTo candidature, HasMany messages, latestMessage, face/producer accessors, and unreadCountFor method
- Created Message model with BelongsTo conversation, MorphTo sender, markAsRead method, isRead helper, and read/unread scopes
- Updated Candidature model with HasOne conversation relationship and hasConversation helper method
- Updated User model with MorphMany sentMessages relationship
- Created ConversationFactory with accepted candidature by default and states for different candidature statuses
- Created MessageFactory with read/unread states and realistic message content states (greeting, missionDetails)
- Created comprehensive test suite with 27 tests covering all 9 acceptance criteria
- All 647 backend tests pass (2766 assertions) - no regressions

### Change Log

| Date       | Author       | Change                                       |
|------------|--------------|----------------------------------------------|
| 2026-01-28 | SM Agent     | Story created - ready-for-dev                |
| 2026-01-28 | Dev Agent    | All tasks implemented - status: review       |
| 2026-01-28 | Review Agent | Code review - 2 MEDIUM issues fixed - done   |

## Senior Developer Review (AI)

**Review Date:** 2026-01-28
**Reviewer:** Claude Opus 4.5 (claude-opus-4-5-20251101)
**Outcome:** ✅ Approved (after fixes)

### Issues Found: 2 MEDIUM, 2 LOW

#### Action Items

- [x] [MEDIUM] Simplified Conversation.unreadCountFor() logic - removed unnecessary orWhere condition
- [x] [MEDIUM] Fixed story Dev Notes - corrected "Pest syntax" to "PHPUnit syntax" (project standard)
- [ ] [LOW] Messages ordering in relationship - acceptable for MVP, no change needed
- [ ] [LOW] No test for null candidature edge case - FK constraint prevents this, no change needed

### Review Notes

- All 9 acceptance criteria properly implemented
- All tasks verified as actually complete
- 27 comprehensive tests covering all ACs
- Migrations correctly define constraints and indexes
- Models follow project patterns (Candidature, Notification)
- Factories properly configured with sensible defaults
- No security issues (schema-only story, no API endpoints)
- All 647 backend tests pass (2766 assertions)

### File List

**Created:**
- `backend/database/migrations/2026_01_27_232542_create_conversations_table.php`
- `backend/database/migrations/2026_01_27_232605_create_messages_table.php`
- `backend/app/Models/Conversation.php`
- `backend/app/Models/Message.php`
- `backend/database/factories/ConversationFactory.php`
- `backend/database/factories/MessageFactory.php`
- `backend/tests/Feature/Messaging/MessagingSchemaTest.php` - 27 tests

**Modified:**
- `backend/app/Models/Candidature.php` - Added conversation() HasOne and hasConversation() method
- `backend/app/Models/User.php` - Added sentMessages() MorphMany relationship
