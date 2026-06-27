import Papa from 'papaparse'

function text(value) {
  return value == null ? '' : String(value).trim()
}

function boolean(value) {
  if (typeof value === 'boolean') return value
  return ['1', 'true', 'ja', 'yes', 'x'].includes(text(value).toLowerCase())
}

export function normalizeStudent(student, index = 0) {
  if (Array.isArray(student)) {
    return {
      position: index + 1,
      studentNumber: text(student[2]),
      fullName: text(student[3]),
      firstName: '',
      lastName: text(student[4]),
      mentor: text(student[5]),
      cumLaude: boolean(student[6]),
      group: text(student[8]),
    }
  }

  return {
    position: index + 1,
    studentNumber: text(student.studentNumber ?? student.Leerlingnummer),
    fullName: text(student.fullName ?? student['Volledige Naam']),
    firstName: text(student.firstName ?? student.Roepnaam ?? student.Voornaam),
    lastName: text(student.lastName ?? student.Achternaam),
    mentor: text(student.mentor ?? student.Afkorting),
    cumLaude: boolean(student.cumLaude ?? student['Cum Laude']),
    group: text(student.group ?? student.Stamgroep),
  }
}

export function normalizeJson(payload) {
  const rows = payload.students ?? payload.body ?? payload.content?.students ?? payload.content?.body
  if (!Array.isArray(rows)) throw new Error('Het JSON-bestand bevat geen leerlingenlijst.')
  return rows.map(normalizeStudent).filter((student) => student.studentNumber && student.fullName)
}

export function parseCsv(csv) {
  const result = Papa.parse(csv, {
    delimiter: ';',
    header: true,
    skipEmptyLines: 'greedy',
  })
  if (result.errors.length) {
    throw new Error(`CSV kon niet volledig worden gelezen: ${result.errors[0].message}`)
  }
  return result.data.map(normalizeStudent).filter((student) => student.studentNumber && student.fullName)
}

export function renumber(students) {
  students.forEach((student, index) => {
    student.position = index + 1
  })
  return students
}

export function selectStudents(students, field, choices) {
  return renumber(
    students
      .filter((student) => choices.includes(student[field]))
      .map((student) => ({ ...student })),
  )
}

export function safeListName(mentors) {
  const base = mentors.length ? mentors.join('_') : 'AlleMentoren'
  return `${base.replace(/[^\p{L}\p{N}_-]+/gu, '_')}.json`
}

export function photoUrl(baseUrl, studentNumber, suffix) {
  return `${import.meta.env.BASE_URL}${baseUrl}/${encodeURIComponent(studentNumber)}_${suffix}.jpg`
}
