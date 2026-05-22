# Pint Formatting Test Report
*Generated: ven. 22 mai 2026 15:02:39 WAT*


  ⨯..⨯⨯..⨯....⨯...⨯⨯..⨯⨯.⨯⨯.⨯⨯⨯.⨯⨯⨯⨯⨯⨯....⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯.⨯.⨯⨯.⨯⨯⨯

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────── Laravel  
    FAIL   ................................................................................................................................................. 64 files, 41 style issues  
  ⨯ src/AbstractDirective.php                                                        class_attributes_separation, new_with_parentheses, no_unused_imports, blank_line_before_statement  
  ⨯ src/Collections/IndexedCollection.php                                        no_superfluous_phpdoc_tags, phpdoc_separation, phpdoc_trim, blank_line_before_statement, phpdoc_align  
  ⨯ src/Collections/ParameterCollection.php                                                                                      new_with_parentheses, phpdoc_separation, phpdoc_align  
  ⨯ src/Contracts/DirectiveInterface.php                                                                                                               phpdoc_separation, phpdoc_align  
  ⨯ src/Contracts/DirectiveRegistrarInterface.php                                                                                   phpdoc_separation, no_unused_imports, phpdoc_align  
  ⨯ src/DirectiveKernel.php                                                                           new_with_parentheses, braces_position, no_unused_imports, single_line_empty_body  
  ⨯ src/DirectiveServiceProvider.php                                                                                                                new_with_parentheses, concat_space  
  ⨯ src/Directives/MakeDirective.php new_with_parentheses, single_quote, concat_space, phpdoc_separation, not_operator_with_successor_space, blank_line_before_statement, phpdoc_alig…  
  ⨯ src/Records/DirectiveLogRecord.php                                                                                        braces_position, single_line_empty_body, ordered_imports  
  ⨯ src/Records/DisplayMessageRecord.php                                                                                      braces_position, single_line_empty_body, ordered_imports  
  ⨯ src/Records/HydrationResultRecord.php                                                                                   braces_position, no_unused_imports, single_line_empty_body  
  ⨯ src/Records/ParsedDirectiveRecord.php                                                                                   braces_position, no_unused_imports, single_line_empty_body  
  ⨯ src/Services/DirectiveDiscoveryService.php concat_space, braces_position, phpdoc_separation, no_unused_imports, not_operator_with_successor_space, single_line_empty_body, phpdoc…  
  ⨯ src/Services/DirectiveExecutionService.php                                                                                                                       no_unused_imports  
  ⨯ src/Services/DirectiveHydratorService.php                                                                               braces_position, no_unused_imports, single_line_empty_body  
  ⨯ src/Services/DirectiveParserService.php          new_with_parentheses, braces_position, phpdoc_separation, not_operator_with_successor_space, single_line_empty_body, phpdoc_align  
  ⨯ src/Services/DirectiveRegistrar.php                                                           new_with_parentheses, not_operator_with_successor_space, blank_line_before_statement  
  ⨯ src/Services/DirectiveRendererService.php                                                                                                                             concat_space  
  ⨯ src/Tasks/AskQuestionTask.php                                                                                                                                         concat_space  
  ⨯ src/Tasks/ConfirmQuestionTask.php                                                                                                                                     concat_space  
  ⨯ src/Tasks/DisplayTableTask.php                                                                                                                  new_with_parentheses, concat_space  
  ⨯ tests/Feature/DirectiveDiscoveryServiceIntegrationTest.php                                        class_attributes_separation, new_with_parentheses, concat_space, ordered_imports  
  ⨯ tests/Feature/DirectiveIntegrationTest.php                                                                                                           concat_space, ordered_imports  
  ⨯ tests/Fixtures/Directives/InvalidClass.php                                                                                                              blank_line_after_namespace  
  ⨯ tests/Fixtures/Directives/TestEchoDirective.php                                                                                                               new_with_parentheses  
  ⨯ tests/Fixtures/Directives/TestPackageDirective.php                                                                               new_with_parentheses, blank_line_before_statement  
  ⨯ tests/Fixtures/Registrars/TestPackageRegistrar.php                                                                               new_with_parentheses, blank_line_before_statement  
  ⨯ tests/TestCase.php                                                                                                                                                    concat_space  
  ⨯ tests/Unit/AbstractDirectiveTest.php                                                         class_attributes_separation, new_with_parentheses, no_unused_imports, ordered_imports  
  ⨯ tests/Unit/Collections/ParameterCollectionTest.php                                                                                                            new_with_parentheses  
  ⨯ tests/Unit/Directives/MakeDirectiveTest.php                                                                                                     no_unused_imports, ordered_imports  
  ⨯ tests/Unit/Enums/DirectiveEventTypeTest.php                                                                                                                        ordered_imports  
  ⨯ tests/Unit/Enums/ExitCodeTest.php                                                                                                                                  ordered_imports  
  ⨯ tests/Unit/Enums/MessageTypeTest.php                                                                                                                               ordered_imports  
  ⨯ tests/Unit/Services/DirectiveExecutionServiceTest.php                              class_attributes_separation, new_with_parentheses, blank_line_before_statement, ordered_imports  
  ⨯ tests/Unit/Services/DirectiveHydratorServiceTest.php class_attributes_separation, new_with_parentheses, trailing_comma_in_multiline, no_unused_imports, blank_line_before_stateme…  
  ⨯ tests/Unit/Services/DirectiveParserServiceTest.php                                                                        new_with_parentheses, no_unused_imports, ordered_imports  
  ⨯ tests/Unit/Services/DirectiveRegistrarTest.php                                                                                 new_with_parentheses, concat_space, ordered_imports  
  ⨯ tests/Unit/Tasks/AskQuestionTaskTest.php                                                                                                                           ordered_imports  
  ⨯ tests/Unit/Tasks/ConfirmQuestionTaskTest.php                                                                                                                       ordered_imports  
  ⨯ tests/Unit/Tasks/DisplayTableTaskTest.php                                                                                                    new_with_parentheses, ordered_imports  

