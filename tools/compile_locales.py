"""Compile singular gettext PO catalogs into MO files using the Python standard library."""
from pathlib import Path
import ast
import gettext
import re
import struct

def read_po(path):
    messages = {}
    current_id = None
    current_value = ''
    field = None
    for raw in path.read_text(encoding='utf-8').splitlines() + ['']:
        line = raw.strip()
        if not line:
            if current_id is not None:
                messages[current_id] = current_value
            current_id, current_value, field = None, '', None
        elif line.startswith('#'):
            if 'fuzzy' in line:
                raise ValueError(f'{path}: resolve fuzzy entries before compilation')
        elif line.startswith('msgid '):
            current_id, field = ast.literal_eval(line[6:]), 'id'
        elif line.startswith('msgstr '):
            current_value, field = ast.literal_eval(line[7:]), 'value'
        elif line.startswith('"') and field:
            if field == 'id':
                current_id += ast.literal_eval(line)
            else:
                current_value += ast.literal_eval(line)
        else:
            raise ValueError(f'{path}: unsupported PO syntax: {line}')
    return messages

def compile_catalog(path):
    messages = read_po(path)
    format_pattern = r'%(?:\d+\$)?[sd]'
    for key, value in messages.items():
        if key and (not value or sorted(re.findall(format_pattern, key)) != sorted(re.findall(format_pattern, value))):
            raise ValueError(f'{path}: missing translation or changed placeholders: {key}')
    pairs = [(key.encode('utf-8'), value.encode('utf-8')) for key, value in sorted(messages.items())]
    count = len(pairs)
    id_table = 28
    value_table = id_table + 8 * count
    data_start = value_table + 8 * count
    ids = b''.join(key + b'\0' for key, _ in pairs)
    values = b''.join(value + b'\0' for _, value in pairs)
    offset = data_start
    id_offsets = []
    for key, _ in pairs:
        id_offsets.append((len(key), offset))
        offset += len(key) + 1
    value_offsets = []
    for _, value in pairs:
        value_offsets.append((len(value), offset))
        offset += len(value) + 1
    binary = struct.pack('<7I', 0x950412de, 0, count, id_table, value_table, 0, 0)
    binary += b''.join(struct.pack('<2I', *row) for row in id_offsets + value_offsets) + ids + values
    target = path.with_suffix('.mo')
    target.write_bytes(binary)
    with target.open('rb') as stream:
        translation = gettext.GNUTranslations(stream)
        for key, value in messages.items():
            if key and translation.gettext(key) != value:
                raise ValueError(f'{target}: round-trip failed: {key}')
    print(f'Compiled and verified {target.name}: {count - 1} messages')

if __name__ == '__main__':
    for source in sorted((Path(__file__).resolve().parents[1] / 'locales').glob('*.po')):
        compile_catalog(source)
