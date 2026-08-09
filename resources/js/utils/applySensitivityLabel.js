import JSZip from 'jszip'

const LABEL_INFO_PATH = 'docMetadata/LabelInfo.xml'
const LABEL_CONTENT_TYPE = 'application/vnd.ms-office.classificationlabels+xml'
const LABEL_REL_TYPE = 'http://schemas.microsoft.com/office/2020/02/relationships/classificationlabels'
const LABEL_REL_ID = 'rIdSensitivityLabel'

/**
 * Excel / Office の秘密度ラベル（Sensitivity Label）を xlsx に埋め込む。
 * 組織固有の labelId / siteId が必要（Public ラベル付き既存ファイルの
 * docMetadata/LabelInfo.xml から取得）。
 *
 * @param {ArrayBuffer} xlsxBuffer
 * @param {{ labelId?: string, siteId?: string, method?: string, contentBits?: number }} options
 * @returns {Promise<ArrayBuffer>}
 */
export async function applySensitivityLabel(xlsxBuffer, options = {}) {
    const labelId = String(options.labelId || '').trim()
    const siteId = String(options.siteId || '').trim()
    if (!labelId || !siteId) {
        return xlsxBuffer
    }

    const method = String(options.method || 'Privileged').trim() || 'Privileged'
    const contentBits = Number.isFinite(Number(options.contentBits))
        ? Number(options.contentBits)
        : 0

    const zip = await JSZip.loadAsync(xlsxBuffer)

    const labelXml = [
        '<?xml version="1.0" encoding="utf-8" standalone="yes"?>',
        '<clbl:labelList xmlns:clbl="http://schemas.microsoft.com/office/2020/mipLabelMetadata">',
        `<clbl:label id="${escapeXml(labelId)}" siteId="${escapeXml(siteId)}" method="${escapeXml(method)}" contentBits="${contentBits}" enabled="1" removed="0" />`,
        '</clbl:labelList>',
    ].join('')
    zip.file(LABEL_INFO_PATH, labelXml)

    const contentTypesFile = zip.file('[Content_Types].xml')
    if (contentTypesFile) {
        let contentTypesXml = await contentTypesFile.async('string')
        if (!contentTypesXml.includes('classificationlabels+xml')) {
            contentTypesXml = contentTypesXml.replace(
                '</Types>',
                `<Override PartName="/${LABEL_INFO_PATH}" ContentType="${LABEL_CONTENT_TYPE}"/></Types>`,
            )
            zip.file('[Content_Types].xml', contentTypesXml)
        }
    }

    const relsFile = zip.file('_rels/.rels')
    if (relsFile) {
        let relsXml = await relsFile.async('string')
        if (!relsXml.includes('classificationlabels')) {
            relsXml = relsXml.replace(
                '</Relationships>',
                `<Relationship Id="${LABEL_REL_ID}" Type="${LABEL_REL_TYPE}" Target="${LABEL_INFO_PATH}"/></Relationships>`,
            )
            zip.file('_rels/.rels', relsXml)
        }
    }

    return zip.generateAsync({ type: 'arraybuffer' })
}

function escapeXml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
}
