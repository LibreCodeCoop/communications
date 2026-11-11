#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
"""Parse compatibility versions from the single canonical Nextcloud app manifest."""
import argparse
import pathlib
import re
import xml.etree.ElementTree as ET

ROOT = pathlib.Path(__file__).resolve().parents[2]


def read_requirements(path=ROOT / 'appinfo' / 'info.xml'):
    info = ET.parse(path).getroot()
    node = info.find('./dependencies/nextcloud')
    php = info.find('./dependencies/php')
    assert node is not None and php is not None, 'Missing PHP or Nextcloud manifest dependencies'
    versions = {
        'nextcloud_min': node.attrib['min-version'],
        'nextcloud_max': node.attrib['max-version'],
        'php_min': php.attrib['min-version'],
    }
    for key, value in versions.items():
        if not re.fullmatch(r'\d+(?:\.\d+){0,2}', value):
            raise ValueError(f'Unexpected {key} version: {value}')
    return versions


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--github-output', action='store_true')
    parser.add_argument('--value', choices=['nextcloud_min', 'nextcloud_max', 'php_min'])
    args = parser.parse_args()
    versions = read_requirements()
    if args.value:
        print(versions[args.value])
    else:
        for name, value in versions.items():
            print(f'{name}={value}')


if __name__ == '__main__':
    main()
