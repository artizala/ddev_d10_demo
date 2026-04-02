// ESLint violations below
const x = 1; // missing semicolon

function badFunc() {
  const unused = x; // unused variable
  return unused;
}

badFunc();
