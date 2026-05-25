# Pint Formatting Test Report
*Generated: mar. 26 mai 2026 00:46:15 WAT*


  ⨯...........................⨯⨯⨯⨯⨯⨯..⨯⨯.............⨯...⨯..⨯...⨯⨯.........⨯...⨯....⨯⨯⨯⨯.....................⨯..⨯..........⨯.⨯....⨯⨯......⨯......!!

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────── Laravel  
    FAIL   ...................................................................................................................................... 145 files, 2 errors, 27 style issues  
  ! app/Directives/TestCommandDirective.php                                                                         Parse error: syntax error, unexpected token "namespace" on line 1.  
  ! app/Directives/UserCreateDirective.php                                                                          Parse error: syntax error, unexpected token "namespace" on line 1.  
  ⨯ src/AbstractDirective.php                                                                                                                                             phpdoc_align  
  ⨯ src/Contracts/DirectiveLoaderInterface.php                                                                                                                       no_unused_imports  
  ⨯ src/DirectiveServiceProvider.php                                                                                                                                      concat_space  
  ⨯ src/Directives/MakeDirective.php class_attributes_separation, fully_qualified_strict_types, concat_space, unary_operator_spaces, not_operator_with_successor_space, blank_line_be…  
  ⨯ src/Services/DirectiveDiscoveryService.php                                                            concat_space, not_operator_with_successor_space, blank_line_before_statement  
  ⨯ src/Services/DirectiveExecutionService.php                                                                    braces_position, single_line_empty_body, blank_line_before_statement  
  ⨯ src/Services/DirectiveParserService.php      function_declaration, concat_space, unary_operator_spaces, braces_position, not_operator_with_successor_space, single_line_empty_body  
  ⨯ src/Strategies/VersionRenderStrategy.php                                                                                           concat_space, not_operator_with_successor_space  
  ⨯ src/Tasks/RenderTask.php                                                                            unary_operator_spaces, not_operator_with_successor_space, no_extra_blank_lines  
  ⨯ src/Testing/ClosureDirective.php                                                                                                                       class_attributes_separation  
  ⨯ src/Testing/DirectiveResponse.php                                          braces_position, not_operator_with_successor_space, single_line_empty_body, blank_line_before_statement  
  ⨯ src/Testing/InteractsWithDirectives.php            class_attributes_separation, new_with_parentheses, concat_space, not_operator_with_successor_space, blank_line_before_statement  
  ⨯ src/Testing/TestDirectiveDiscoveryService.php                                                                       not_operator_with_successor_space, blank_line_before_statement  
  ⨯ src/Testing/TestDirectiveRegistry.php                                                               class_attributes_separation, new_with_parentheses, blank_line_before_statement  
  ⨯ src/Traits/FileCreator.php                                     new_with_parentheses, concat_space, not_operator_with_successor_space, blank_line_before_statement, ordered_imports  
  ⨯ tests/Feature/DirectiveIntegrationTest.php                                                                                                                            concat_space  
  ⨯ tests/Fixtures/Directives/AnotherTestDirective.php                                                                                                            new_with_parentheses  
  ⨯ tests/Fixtures/Directives/TestCalculatorDirective.php                                                                                                  blank_line_before_statement  
  ⨯ tests/Fixtures/Directives/TestDirectiveWithArgs.php                                                                                                           new_with_parentheses  
  ⨯ tests/IntegrationTestCase.php                                                                               concat_space, unary_operator_spaces, not_operator_with_successor_space  
  ⨯ tests/Unit/Directives/MakeDirectiveTest.php                                                                            fully_qualified_strict_types, concat_space, ordered_imports  
  ⨯ tests/Unit/Services/DirectiveDiscoveryServiceTest.php                                                        concat_space, not_operator_with_successor_space, no_extra_blank_lines  
  ⨯ tests/Unit/Services/DirectiveExecutionServiceTest.php                                                                                                                 concat_space  
  ⨯ tests/Unit/Testing/ClosureDirectiveTest.php class_attributes_separation, new_with_parentheses, function_declaration, fully_qualified_strict_types, blank_line_before_statement, o…  
  ⨯ tests/Unit/Testing/InteractsWithDirectivesTest.php                                                                                       concat_space, blank_line_before_statement  
  ⨯ tests/Unit/Testing/TestDirectiveDiscoveryServiceTest.php                                                                                               class_attributes_separation  
  ⨯ tests/Unit/Traits/FileCreatorTest.php                                                                 class_attributes_separation, concat_space, not_operator_with_successor_space  

