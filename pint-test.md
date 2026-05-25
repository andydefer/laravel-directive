# Pint Formatting Test Report
*Generated: lun. 25 mai 2026 15:16:29 WAT*


  ............................⨯..⨯⨯..................................⨯........⨯...................................⨯..................!!

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────── Laravel  
    FAIL   ....................................................................................................................................... 133 files, 2 errors, 6 style issues  
  ! app/Directives/TestCommandDirective.php                                                                         Parse error: syntax error, unexpected token "namespace" on line 1.  
  ! app/Directives/UserCreateDirective.php                                                                          Parse error: syntax error, unexpected token "namespace" on line 1.  
  ⨯ src/Directives/MakeDirective.php class_attributes_separation, fully_qualified_strict_types, concat_space, unary_operator_spaces, not_operator_with_successor_space, blank_line_be…  
  ⨯ src/Strategies/VersionRenderStrategy.php                                                                                           concat_space, not_operator_with_successor_space  
  ⨯ src/Tasks/RenderTask.php                                                                            unary_operator_spaces, not_operator_with_successor_space, no_extra_blank_lines  
  ⨯ src/Traits/FileCreator.php                                     new_with_parentheses, concat_space, not_operator_with_successor_space, blank_line_before_statement, ordered_imports  
  ⨯ tests/Unit/Directives/MakeDirectiveTest.php                                                           class_attributes_separation, concat_space, not_operator_with_successor_space  
  ⨯ tests/Unit/Traits/FileCreatorTest.php                                                                 class_attributes_separation, concat_space, not_operator_with_successor_space  

