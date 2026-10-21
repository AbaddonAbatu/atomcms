import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const template = readFileSync(new URL('../../resources/themes/dusk/views/components/navigation/dropdown.blade.php', import.meta.url), 'utf8');
const stateExpression = template.match(/\bx-data="([\s\S]*?)"/)?.[1];
assert.ok(stateExpression, 'The dropdown must expose its inline Alpine state');
const navigationDropdown = new Function('window', `return (${stateExpression});`);

function createDropdown({ desktop = false, navigation = true } = {}) {
    const ownerDocument = { activeElement: null };
    const button = {
        ownerDocument,
        focus() { ownerDocument.activeElement = this; },
        closest() { return navigation ? {} : null; },
    };
    const item = { focus() { ownerDocument.activeElement = this; } };
    const dropdown = navigationDropdown({ matchMedia: () => ({ matches: desktop }) });
    dropdown.$nextTick = (callback) => callback();
    dropdown.$refs = {
        button,
        panel: {
            contains: (element) => element === item,
            querySelector: () => item,
        },
    };

    return { dropdown, button, item, ownerDocument };
}

test('the href trigger wires ArrowDown to an idempotent keyboard opener and focuses the first item', () => {
    const trigger = template.match(/<a\s+x-ref="button"([\s\S]*?)<\/a>/)?.[1];
    assert.match(trigger ?? '', /x-on:keydown\.down="openFromKeyboard\(\$event\)"/);
    assert.match(trigger ?? '', /:aria-expanded="open"/);
    assert.match(trigger ?? '', /:aria-controls="\$id\('dropdown-button'\)"/);

    const { dropdown, button, item, ownerDocument } = createDropdown({ desktop: true });
    const event = { preventDefault() { this.defaultPrevented = true; } };

    dropdown.openFromKeyboard(event);
    dropdown.openFromKeyboard(event);
    dropdown.openOnHover({ pointerType: 'mouse' });
    dropdown.closeOnLeave({ pointerType: 'mouse' });

    assert.equal(event.defaultPrevented, true);
    assert.equal(dropdown.open, true);
    assert.equal(dropdown.openedByHover, false);
    assert.equal(ownerDocument.activeElement, item);
    assert.notEqual(ownerDocument.activeElement, button);
});

test('mouse hover opens without stealing focus and pointer departure closes', () => {
    const { dropdown, ownerDocument } = createDropdown();

    dropdown.openOnHover({ pointerType: 'mouse' });

    assert.equal(dropdown.open, true);
    assert.equal(ownerDocument.activeElement, null);

    dropdown.closeOnLeave({ pointerType: 'mouse' });

    assert.equal(dropdown.open, false);
});

test('touch and pen pointer entry do not open the menu', () => {
    const { dropdown } = createDropdown();

    for (const pointerType of ['touch', 'pen']) {
        dropdown.openOnHover({ pointerType });
        assert.equal(dropdown.open, false);
    }
});

test('mobile mouse clicks keep a hover-open menu open and a second click closes it', () => {
    const { dropdown, button, ownerDocument } = createDropdown();
    dropdown.openOnHover({ pointerType: 'mouse' });

    dropdown.toggle({ pointerType: 'mouse', currentTarget: button });
    dropdown.closeOnLeave({ pointerType: 'mouse' });

    assert.equal(dropdown.open, true);
    assert.equal(ownerDocument.activeElement, button);

    dropdown.toggle({ pointerType: 'mouse', currentTarget: button });

    assert.equal(dropdown.open, false);
});

test('desktop keyboard activation survives mouse entry and departure', () => {
    const { dropdown, button } = createDropdown({ desktop: true });
    dropdown.toggle({ pointerType: '', detail: 0, currentTarget: button });

    dropdown.openOnHover({ pointerType: 'mouse' });
    dropdown.closeOnLeave({ pointerType: 'mouse' });

    assert.equal(dropdown.open, true);
});

test('desktop mouse clicks do not pin a hover-open navigation dropdown', () => {
    const { dropdown, button } = createDropdown({ desktop: true });
    dropdown.openOnHover({ pointerType: 'mouse' });

    dropdown.toggle({ pointerType: 'mouse', currentTarget: button });
    dropdown.closeOnLeave({ pointerType: 'mouse' });

    assert.equal(dropdown.open, false);
});

test('desktop mouse clicks do not open a navigation dropdown without hover', () => {
    const { dropdown, button } = createDropdown({ desktop: true });

    dropdown.toggle({ pointerType: 'mouse', currentTarget: button });

    assert.equal(dropdown.open, false);
});

test('touch activation still toggles a navigation dropdown on desktop', () => {
    const { dropdown, button } = createDropdown({ desktop: true });

    dropdown.toggle({ pointerType: 'touch', currentTarget: button });
    dropdown.closeOnLeave({ pointerType: 'mouse' });

    assert.equal(dropdown.open, true);

    dropdown.toggle({ pointerType: 'touch', currentTarget: button });

    assert.equal(dropdown.open, false);
});

test('the language selector retains desktop mouse click activation', () => {
    const { dropdown, button } = createDropdown({ desktop: true, navigation: false });

    dropdown.toggle({ pointerType: 'mouse', currentTarget: button });
    dropdown.closeOnLeave({ pointerType: 'mouse' });

    assert.equal(dropdown.open, true);
});

test('the mobile Home arrow receives focus when opening and dismissing its dropdown', () => {
    const { dropdown, button, item, ownerDocument } = createDropdown();
    const toggleButton = { ...button, offsetParent: {} };
    dropdown.$refs.toggleButton = toggleButton;

    dropdown.toggle({ pointerType: 'touch', currentTarget: toggleButton });

    assert.equal(ownerDocument.activeElement, toggleButton);

    item.focus();
    dropdown.close(dropdown.focusTrigger());

    assert.equal(dropdown.open, false);
    assert.equal(ownerDocument.activeElement, toggleButton);
});

test('desktop dismissal returns focus to Home instead of its hidden mobile arrow', () => {
    const { dropdown, button, item, ownerDocument } = createDropdown({ desktop: true });
    dropdown.$refs.toggleButton = { ...button, offsetParent: null };
    dropdown.openOnHover({ pointerType: 'mouse' });
    item.focus();

    dropdown.close(dropdown.focusTrigger());

    assert.equal(ownerDocument.activeElement, button);
});

test('pointer departure keeps the menu open while a child has keyboard focus', () => {
    const { dropdown, item } = createDropdown();
    dropdown.openOnHover({ pointerType: 'mouse' });
    item.focus();

    dropdown.closeOnLeave({ pointerType: 'mouse' });

    assert.equal(dropdown.open, true);
});

test('Escape dismissal returns focus to the trigger and clears hover state', () => {
    const { dropdown, button, item, ownerDocument } = createDropdown();
    dropdown.openOnHover({ pointerType: 'mouse' });
    item.focus();

    dropdown.close(button);

    assert.equal(dropdown.open, false);
    assert.equal(dropdown.openedByHover, false);
    assert.equal(ownerDocument.activeElement, button);
});

test('outside dismissal preserves focus on another control', () => {
    const { dropdown, ownerDocument } = createDropdown();
    dropdown.openOnHover({ pointerType: 'mouse' });
    const outsideControl = {};
    ownerDocument.activeElement = outsideControl;

    dropdown.close();

    assert.equal(dropdown.open, false);
    assert.equal(ownerDocument.activeElement, outsideControl);
});
